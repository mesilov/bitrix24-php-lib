<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Journal\Infrastructure\Doctrine;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class DoctrineDbalJournalItemRepository implements JournalItemRepositoryInterface
{
    private readonly EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(JournalItem::class);
    }

    #[\Override]
    public function save(JournalItemInterface $journalItem): void
    {
        $this->entityManager->persist($journalItem);
    }

    #[\Override]
    public function findById(Uuid $uuid): ?JournalItemInterface
    {
        return $this->repository->find($uuid);
    }

    /**
     * @return JournalItemInterface[]
     */
    #[\Override]
    public function findByApplicationInstallationId(
        string $memberId,
        Uuid $applicationInstallationId,
        ?LogLevel $logLevel = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $queryBuilder = $this->repository
            ->createQueryBuilder('j')
            ->where('j.memberId = :memberId')
            ->setParameter('memberId', $memberId)
            ->andWhere('j.applicationInstallationId = :appId')
            ->setParameter('appId', $applicationInstallationId)
            ->orderBy('j.createdAt', 'DESC')
        ;

        if (null !== $logLevel) {
            $queryBuilder->andWhere('j.level = :level')
                ->setParameter('level', $logLevel)
            ;
        }

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        if (null !== $offset) {
            $queryBuilder->setFirstResult($offset);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return JournalItemInterface[]
     */
    #[\Override]
    public function findByMemberId(
        string $memberId,
        ?LogLevel $logLevel = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $queryBuilder = $this->repository
            ->createQueryBuilder('j')
            ->where('j.memberId = :memberId')
            ->setParameter('memberId', $memberId)
            ->orderBy('j.createdAt', 'DESC')
        ;

        if (null !== $logLevel) {
            $queryBuilder->andWhere('j.level = :level')
                ->setParameter('level', $logLevel)
            ;
        }

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        if (null !== $offset) {
            $queryBuilder->setFirstResult($offset);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    #[\Override]
    public function deleteOlderThan(
        string $memberId,
        Uuid $applicationInstallationId,
        CarbonImmutable $date
    ): int {
        return $this->entityManager->createQueryBuilder()
            ->delete(JournalItem::class, 'j')
            ->where('j.memberId = :memberId')
            ->andWhere('j.applicationInstallationId = :appId')
            ->andWhere('j.createdAt < :date')
            ->setParameter('memberId', $memberId)
            ->setParameter('appId', $applicationInstallationId)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute()
        ;
    }
}
