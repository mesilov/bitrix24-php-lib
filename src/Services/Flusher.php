<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Services;

use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\InactiveInstallationException;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Flusher
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EventDispatcherInterface $eventDispatcher) {}

    public function flush(AggregateRootEventsEmitterInterface ...$aggregateRootEventsEmitter): void
    {
        $connection = $this->em->getConnection();
        $ownTransaction = !$connection->isTransactionActive();
        if ($ownTransaction) {
            $connection->beginTransaction();
        }

        try {
            LifecycleWriteGuard::lock($this->em);
            $this->em->flush();
            if ($ownTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $throwable) {
            if ($ownTransaction) {
                $connection->rollBack();
                $this->em->close();
            }

            throw $throwable;
        }

        foreach ($aggregateRootEventsEmitter as $aggregateRootEventEmitter) {
            $events = $aggregateRootEventEmitter->emitEvents();
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }
    }

    /** Serialize installation, uninstall and generation-bound account creation. */
    public function withPortalLock(string $memberId, \Closure $operation, ?Uuid $activeInstallationId = null, #[\SensitiveParameter] ?string $applicationToken = null): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            throw new \LogicException('Portal lifecycle operation requires its own transaction.');
        }

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        if ([] !== $unitOfWork->getScheduledEntityInsertions() || [] !== $unitOfWork->getScheduledEntityUpdates() || [] !== $unitOfWork->getScheduledEntityDeletions()) {
            throw new \LogicException('Flush unrelated changes before a portal lifecycle operation.');
        }

        $this->em->clear();
        $connection->beginTransaction();

        try {
            LifecycleLock::acquire($connection, 'member:'.$memberId);
            if (null !== $applicationToken && false !== $connection->fetchOne('SELECT installation_id FROM b24lib_uninstallations WHERE application_token_hash = ?', [hash('sha256', $applicationToken)])) {
                throw new InactiveInstallationException();
            }

            if (null !== $activeInstallationId) {
                $active = $connection->fetchOne(
                    'SELECT i.id FROM b24lib_application_installations i INNER JOIN b24lib_bitrix24_accounts a ON a.id = i.bitrix_24_account_id WHERE i.id = ? AND i.status = ? AND a.member_id = ?',
                    [$activeInstallationId->toRfc4122(), 'active', $memberId]
                );
                if (false === $active) {
                    throw new InactiveInstallationException();
                }
            }

            $operation();
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            $this->em->close();

            throw $throwable;
        }
    }
}
