<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Entity\Uninstallation;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\ApplicationDataCleanerInterface;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationContext;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationFailedException;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationStoreInterface;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Command;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Services\LifecycleLock;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

final readonly class UninstallationStore implements UninstallationStoreInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    #[\Override]
    public function begin(#[\SensitiveParameter] Command $command): ?UninstallationContext
    {
        $this->guardStandaloneOperation();

        try {
            return $this->transaction(function () use ($command): ?UninstallationContext {
                $receipt = $this->em->find(Uninstallation::class, hash('sha256', $command->applicationToken), LockMode::PESSIMISTIC_WRITE);
                if ($receipt instanceof Uninstallation) {
                    return null === $receipt->getCompletedAt() ? $receipt->contextFor($command->memberId) : null;
                }

                $installation = $this->em->createQueryBuilder()
                    ->select('i')
                    ->from(ApplicationInstallation::class, 'i')
                    ->where('i.applicationToken = :token')
                    ->andWhere('i.status IN (:statuses)')
                    ->setParameter('token', $command->applicationToken)
                    ->setParameter('statuses', [ApplicationInstallationStatus::active, ApplicationInstallationStatus::blocked])
                    ->getQuery()
                    ->getOneOrNullResult()
                ;
                if (!$installation instanceof ApplicationInstallation) {
                    return null;
                }

                $installationId = $installation->getId();
                LifecycleLock::acquire($this->em->getConnection(), 'installation:'.$installationId->toRfc4122());
                $this->em->clear();
                $installation = $this->em->find(ApplicationInstallation::class, $installationId, LockMode::PESSIMISTIC_WRITE);
                if (!$installation instanceof ApplicationInstallation
                    || !in_array($installation->getStatus(), [ApplicationInstallationStatus::active, ApplicationInstallationStatus::blocked], true)
                    || !$installation->isApplicationTokenValid($command->applicationToken)) {
                    return null;
                }

                $master = $this->em->find(Bitrix24Account::class, $installation->getBitrix24AccountId(), LockMode::PESSIMISTIC_WRITE);
                if (!$master instanceof Bitrix24Account || !hash_equals($master->getMemberId(), $command->memberId)) {
                    return null;
                }

                // Reject a stale generation before deleting the portal's accounts.
                if ($this->hasOtherInstallations($installation, $master->getMemberId())) {
                    return null;
                }

                $this->removePortalAccounts($master->getMemberId());

                $installation->applicationUninstalled($command->applicationToken);
                $installation->forgetUninstallToken();
                // Cleanup is mandatory; legacy domain events are replaced by the post-commit notification.
                $installation->emitEvents();

                $receipt = new Uninstallation($installation->getId(), $command->applicationToken, $master->getMemberId(), $command->clean);
                $this->em->persist($receipt);
                $this->em->flush();

                return $receipt->contextFor($master->getMemberId());
            }, $command->memberId);
        } catch (\Throwable) {
            throw new UninstallationFailedException('Unable to revoke installation authorization; retry the uninstall event.');
        }
    }

    #[\Override]
    public function complete(UninstallationContext $context, ApplicationDataCleanerInterface $cleaner): bool
    {
        $this->guardStandaloneOperation();

        try {
            return $this->transaction(function () use ($context, $cleaner): bool {
                LifecycleLock::acquire($this->em->getConnection(), 'installation:'.$context->installationId->toRfc4122());
                $receipt = $this->em->createQueryBuilder()
                    ->select('r')
                    ->from(Uninstallation::class, 'r')
                    ->where('r.installationId = :id')
                    ->setParameter('id', $context->installationId)
                    ->getQuery()
                    ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                    ->getOneOrNullResult()
                ;
                if (!$receipt instanceof Uninstallation || null !== $receipt->getCompletedAt()) {
                    return false;
                }

                $trustedContext = $receipt->contextFor($context->memberId);
                if (null === $trustedContext) {
                    return false;
                }

                $installation = $this->em->find(ApplicationInstallation::class, $trustedContext->installationId, LockMode::PESSIMISTIC_WRITE);
                if (!$installation instanceof ApplicationInstallation || ApplicationInstallationStatus::deleted !== $installation->getStatus()) {
                    throw new \LogicException('Pending cleanup installation is unavailable.');
                }

                $cleaner->clean($trustedContext);
                if ($trustedContext->clean) {
                    $this->removeInstallationData($installation);
                }

                $this->em->remove($installation);
                $receipt->complete();
                $this->em->flush();

                return true;
            }, $context->memberId);
        } catch (\Throwable) {
            throw new UninstallationFailedException('Application data cleanup failed; retry the uninstall event.');
        }
    }

    private function hasOtherInstallations(ApplicationInstallation $installation, string $memberId): bool
    {
        $otherInstallations = $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(ApplicationInstallation::class, 'i')
            ->innerJoin(Bitrix24Account::class, 'a', Join::WITH, 'i.bitrix24AccountId = a.id')
            ->where('a.memberId = :member')
            ->andWhere('i.id != :id')
            ->andWhere('i.status != :deleted')
            ->setParameter('member', $memberId)
            ->setParameter('id', $installation->getId())
            ->setParameter('deleted', ApplicationInstallationStatus::deleted)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $otherInstallations > 0;
    }

    private function removePortalAccounts(string $memberId): void
    {
        $accounts = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Bitrix24Account::class, 'a')
            ->where('a.memberId = :member')
            ->setParameter('member', $memberId)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult()
        ;
        foreach ($accounts as $account) {
            $this->em->remove($account);
        }
    }

    private function removeInstallationData(ApplicationInstallation $installation): void
    {
        foreach ([ApplicationSettingsItem::class, JournalItem::class] as $class) {
            // No status filter: soft-deleted records still contain user data.
            foreach ($this->em->getRepository($class)->findBy(['applicationInstallationId' => $installation->getId()]) as $entity) {
                $this->em->remove($entity);
            }
        }

        $contactIds = [];
        foreach ([$installation->getContactPersonId(), $installation->getBitrix24PartnerContactPersonId()] as $id) {
            if (null !== $id) {
                $contactIds[$id->toRfc4122()] = $id;
            }
        }

        ksort($contactIds);
        foreach ($contactIds as $contactId) {
            LifecycleLock::acquire($this->em->getConnection(), 'contact:'.$contactId->toRfc4122());
            $contact = $this->em->find(ContactPerson::class, $contactId, LockMode::PESSIMISTIC_WRITE);
            if (!$contact instanceof ContactPerson || null !== $contact->getBitrix24PartnerId()) {
                continue;
            }

            $references = $this->em->createQueryBuilder()
                ->select('COUNT(i.id)')
                ->from(ApplicationInstallation::class, 'i')
                ->where('i.id != :installation')
                ->andWhere('(i.contactPersonId = :contact OR i.bitrix24PartnerContactPersonId = :contact)')
                ->setParameter('installation', $installation->getId())
                ->setParameter('contact', $contactId)
                ->getQuery()
                ->getSingleScalarResult()
            ;
            if (0 === (int) $references) {
                $this->em->remove($contact);
            }
        }
    }

    private function guardStandaloneOperation(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Uninstall requires its own transactions so authorization revocation cannot be rolled back by the caller.');
        }

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        if ([] !== $unitOfWork->getScheduledEntityInsertions()
            || [] !== $unitOfWork->getScheduledEntityUpdates()
            || [] !== $unitOfWork->getScheduledEntityDeletions()) {
            throw new \LogicException('Flush unrelated changes before processing uninstall.');
        }

        // Reload under locks without refreshing initialized readonly properties.
        $this->em->clear();
    }

    private function transaction(\Closure $operation, string $memberId): mixed
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            LifecycleLock::acquire($connection, 'member:'.$memberId);
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            $this->em->close();

            throw $throwable;
        }
    }
}
