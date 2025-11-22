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

class DoctrineDbalJournalItemRepository extends EntityRepository implements JournalItemRepositoryInterface
{
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager, $entityManager->getClassMetadata(JournalItem::class));
    }

    #[\Override]
    public function save(JournalItemInterface $journalItem): void
    {
        $this->getEntityManager()->persist($journalItem);
    }

    #[\Override]
    public function findById(Uuid $id): ?JournalItemInterface
    {
        return $this->getEntityManager()->getRepository(JournalItem::class)->find($id);
    }

    /**
     * @return JournalItemInterface[]
     */
    #[\Override]
    public function findByApplicationInstallationId(
        Uuid $applicationInstallationId,
        ?LogLevel $level = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $qb = $this->getEntityManager()->getRepository(JournalItem::class)
            ->createQueryBuilder('j')
            ->where('j.applicationInstallationId = :appId')
            ->setParameter('appId', $applicationInstallationId)
            ->orderBy('j.createdAt', 'DESC');

        if (null !== $level) {
            $qb->andWhere('j.level = :level')
                ->setParameter('level', $level);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        if (null !== $offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    #[\Override]
    public function deleteByApplicationInstallationId(Uuid $applicationInstallationId): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->delete(JournalItem::class, 'j')
            ->where('j.applicationInstallationId = :appId')
            ->setParameter('appId', $applicationInstallationId)
            ->getQuery()
            ->execute();
    }

    #[\Override]
    public function deleteOlderThan(CarbonImmutable $date): int
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->delete(JournalItem::class, 'j')
            ->where('j.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    #[\Override]
    public function countByApplicationInstallationId(Uuid $applicationInstallationId, ?LogLevel $level = null): int
    {
        $qb = $this->getEntityManager()->getRepository(JournalItem::class)
            ->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.applicationInstallationId = :appId')
            ->setParameter('appId', $applicationInstallationId);

        if (null !== $level) {
            $qb->andWhere('j.level = :level')
                ->setParameter('level', $level);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
