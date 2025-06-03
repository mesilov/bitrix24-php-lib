<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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

        if (ApplicationInstallationStatus::deleted !== $applicationInstallation->getStatus()) {
            throw new InvalidArgumentException(
                sprintf('you cannot delete application installed because you status must be deleted your status %s', $applicationInstallation->getStatus()->name)
            );
        }

        $this->save($applicationInstallation);
    }

    #[\Override]
    //У нас в установке аккаунтId это констрейнт, так что возращать мы должны сущность.
    public function findByBitrix24AccountId(Uuid $uuid): ApplicationInstallationInterface|null
    {
        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.bitrix24AccountId = :bitrix24AccountId')
            ->setParameter('bitrix24AccountId', $uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findActiveApplicationInstallations(string $memberId): array
    {
        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.status IN (:statuses)')
            ->andWhere('appInstallation.memberId = :memberId')
            ->setParameter('statuses', [ApplicationInstallationStatus::active, ApplicationInstallationStatus::new])
            ->setParameter('memberId', $memberId)
            ->getQuery()
            ->getResult()
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
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findActiveByAccountId(Uuid $b24AccountId): ApplicationInstallationInterface|null
    {
        $activeStatuses = [
            ApplicationInstallationStatus::new,
            ApplicationInstallationStatus::active,
        ];

        $installation = $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('applicationInstallation')
            ->where('applicationInstallation.bitrix24AccountId = :b24AccountId')
            ->andWhere('applicationInstallation.status IN (:statuses)')
            ->setParameter('b24AccountId', $b24AccountId)
            ->setParameter('statuses', $activeStatuses)
            ->getQuery()
            ->getOneOrNullResult();

        return $installation;
    }
}
