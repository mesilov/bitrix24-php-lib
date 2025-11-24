<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Exceptions\Bitrix24PartnerNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class Bitrix24PartnerRepository extends EntityRepository implements Bitrix24PartnerRepositoryInterface
{
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager, $entityManager->getClassMetadata(Bitrix24Partner::class));
    }

    /**
     * @phpstan-return Bitrix24PartnerInterface&AggregateRootEventsEmitterInterface
     *
     * @throws Bitrix24PartnerNotFoundException
     */
    #[\Override]
    public function getById(Uuid $uuid): Bitrix24PartnerInterface
    {
        $partner = $this->getEntityManager()->getRepository(Bitrix24Partner::class)
            ->createQueryBuilder('p')
            ->where('p.id = :id')
            ->andWhere('p.status != :status')
            ->setParameter('id', $uuid)
            ->setParameter('status', Bitrix24PartnerStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $partner) {
            throw new Bitrix24PartnerNotFoundException(
                sprintf('bitrix24 partner not found by id %s', $uuid->toRfc4122())
            );
        }

        return $partner;
    }

    #[\Override]
    public function save(Bitrix24PartnerInterface $bitrix24Partner): void
    {
        $this->getEntityManager()->persist($bitrix24Partner);
    }

    /**
     * @throws InvalidArgumentException
     * @throws Bitrix24PartnerNotFoundException
     */
    #[\Override]
    public function delete(Uuid $uuid): void
    {
        $bitrix24Partner = $this->getEntityManager()->getRepository(Bitrix24Partner::class)->find($uuid);

        if (null === $bitrix24Partner) {
            throw new Bitrix24PartnerNotFoundException(
                sprintf('bitrix24 partner not found by id %s', $uuid->toRfc4122())
            );
        }

        if (Bitrix24PartnerStatus::deleted !== $bitrix24Partner->getStatus()) {
            throw new InvalidArgumentException(
                sprintf(
                    'you cannot delete bitrix24 partner «%s», they must be in status «deleted», current status «%s»',
                    $bitrix24Partner->getId()->toRfc4122(),
                    $bitrix24Partner->getStatus()->name
                )
            );
        }

        $this->getEntityManager()->remove($bitrix24Partner);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function findByBitrix24PartnerId(int $bitrix24PartnerId): ?Bitrix24PartnerInterface
    {
        if ($bitrix24PartnerId < 0) {
            throw new InvalidArgumentException('bitrix24PartnerId cannot be negative');
        }

        return $this->getEntityManager()->getRepository(Bitrix24Partner::class)
            ->createQueryBuilder('p')
            ->where('p.bitrix24PartnerId = :partnerId')
            ->andWhere('p.status != :status')
            ->setParameter('partnerId', $bitrix24PartnerId)
            ->setParameter('status', Bitrix24PartnerStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return array<Bitrix24PartnerInterface>
     *
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function findByTitle(string $title): array
    {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('title cannot be empty');
        }

        return $this->getEntityManager()->getRepository(Bitrix24Partner::class)
            ->createQueryBuilder('p')
            ->where('p.title LIKE :title')
            ->andWhere('p.status != :status')
            ->setParameter('title', '%' . $title . '%')
            ->setParameter('status', Bitrix24PartnerStatus::deleted)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Bitrix24PartnerInterface>
     *
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function findByExternalId(string $externalId, ?Bitrix24PartnerStatus $status = null): array
    {
        if ('' === trim($externalId)) {
            throw new InvalidArgumentException('externalId cannot be empty');
        }

        $qb = $this->getEntityManager()->getRepository(Bitrix24Partner::class)
            ->createQueryBuilder('p')
            ->where('p.externalId = :externalId')
            ->setParameter('externalId', $externalId);

        if (null !== $status) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        } else {
            $qb->andWhere('p.status != :status')
                ->setParameter('status', Bitrix24PartnerStatus::deleted);
        }

        return $qb->getQuery()->getResult();
    }
}
