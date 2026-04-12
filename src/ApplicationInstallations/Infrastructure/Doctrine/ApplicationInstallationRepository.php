<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Uid\Uuid;

class ApplicationInstallationRepository extends EntityRepository implements ApplicationInstallationRepositoryInterface
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(ApplicationInstallation::class));
    }

    #[\Override]
    public function save(ApplicationInstallationInterface $applicationInstallation): void
    {
        $this->getEntityManager()->persist($applicationInstallation);
    }

    #[\Override]
    public function getById(Uuid $uuid): ApplicationInstallationInterface
    {
        $applicationInstallation = $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.id = :uuid')
            ->andWhere('appInstallation.status != :status')
            ->setParameter('uuid', $uuid)
            ->setParameter('status', ApplicationInstallationStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $applicationInstallation) {
            throw new ApplicationInstallationNotFoundException(
                sprintf('application installed not found by id %s', $uuid->toRfc4122())
            );
        }

        return $applicationInstallation;
    }

    #[\Override]
    public function delete(Uuid $uuid): void
    {
        $applicationInstallation = $this->getEntityManager()->getRepository(ApplicationInstallation::class)->find($uuid);

        if (null == $applicationInstallation) {
            throw new ApplicationInstallationNotFoundException(
                sprintf('Application installation with uuid %s not found', $uuid->toRfc4122())
            );
        }

        if (ApplicationInstallationStatus::deleted !== $applicationInstallation->getStatus()) {
            throw new InvalidArgumentException(
                sprintf('you cannot delete application installed because you status must be deleted your status %s', $applicationInstallation->getStatus()->name)
            );
        }

        $this->save($applicationInstallation);
    }

    #[\Override]
    // In our installation, accountId is a constraint, so we must return an entity.
    public function findByBitrix24AccountId(Uuid $uuid): ?ApplicationInstallationInterface
    {
        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.bitrix24AccountId = :bitrix24AccountId')
            ->orderBy('appInstallation.createdAt', 'DESC')
            ->setParameter('bitrix24AccountId', $uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findByExternalId(string $externalId): array
    {
        if ('' === trim($externalId)) {
            throw new InvalidArgumentException('external id cannot be empty');
        }

        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.externalId = :externalId')
            ->orderBy('appInstallation.createdAt', 'DESC')
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get the current installation on the portal without input parameters.
     * The system allows only one active installation per portal,
     * therefore, the current one is interpreted as the installation with status active.
     * If, for any reason, there are multiple, select the most recent by createdAt.
     *
     * @throws ApplicationInstallationNotFoundException
     */
    public function getCurrent(): ApplicationInstallationInterface
    {
        $applicationInstallation = $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.status = :status')
            ->orderBy('appInstallation.createdAt', 'DESC')
            ->setParameter('status', ApplicationInstallationStatus::active)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $applicationInstallation) {
            throw new ApplicationInstallationNotFoundException('current active application installation not found');
        }

        return $applicationInstallation;
    }

    /**
     * Find application installation by application token.
     *
     * TODO: Create issue in b24-php-sdk to add this method to ApplicationInstallationRepositoryInterface
     *
     * @param non-empty-string $applicationToken
     *
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function findByApplicationToken(string $applicationToken): ?ApplicationInstallationInterface
    {
        if ('' === trim($applicationToken)) {
            throw new InvalidArgumentException('application token cannot be an empty string');
        }

        $activeStatuses = [
            ApplicationInstallationStatus::new,
            ApplicationInstallationStatus::active,
        ];

        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('applicationInstallation')
            ->where('applicationInstallation.applicationToken = :applicationToken')
            ->andWhere('applicationInstallation.status IN (:statuses)')
            ->setParameter('applicationToken', $applicationToken)
            ->setParameter('statuses', $activeStatuses)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findByBitrix24AccountMemberId(string $memberId): ?ApplicationInstallationInterface
    {
        if ('' === trim($memberId)) {
            throw new InvalidArgumentException('memberId cannot be an empty string');
        }

        $queryBuilder = $this->createQueryBuilder('ai');

        return $queryBuilder->leftJoin(
            Bitrix24Account::class,
            'b24',
            Join::WITH,
            'ai.bitrix24AccountId = b24.id AND b24.isMasterAccount = true'
        )
            ->where('b24.memberId = :memberId')
            ->andWhere('b24.isMasterAccount = true')
            ->andWhere('ai.status != :status')
            ->setParameter('memberId', $memberId)
            ->setParameter('status', ApplicationInstallationStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
