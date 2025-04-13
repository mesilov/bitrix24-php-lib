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

        if (null === $applicationInstallation) {
            throw new ApplicationInstallationNotFoundException(
                sprintf('application installed not found by id %s', $uuid->toRfc4122())
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
    public function findByBitrix24AccountId(Uuid $uuid): array
    {
        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.bitrix24AccountId = :bitrix24AccountId')
            ->setParameter('bitrix24AccountId', $uuid)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findActiveApplicationInstallations(): array
    {
        return $this->getEntityManager()->getRepository(ApplicationInstallation::class)
            ->createQueryBuilder('appInstallation')
            ->where('appInstallation.status IN (:statuses)')
            ->setParameter('statuses', [ApplicationInstallationStatus::active, ApplicationInstallationStatus::new])
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
}
