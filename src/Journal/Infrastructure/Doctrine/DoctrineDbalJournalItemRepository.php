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

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Uid\Uuid;

class DoctrineDbalJournalItemRepository implements JournalItemRepositoryInterface
{
    private readonly EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaginatorInterface $paginator
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
        ?string $logLevel = null,
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
        ?string $logLevel = null,
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

    /**
     * Find journal items with filters and pagination.
     *
     * @return PaginationInterface<JournalItemInterface>
     */
    public function findWithFilters(
        ?string $memberId = null,
        ?Domain $domain = null,
        ?string $logLevel = null,
        ?string $label = null,
        int $page = 1,
        int $limit = 50
    ): PaginationInterface {
        $queryBuilder = $this->createFilteredQueryBuilder($memberId, $domain, $logLevel, $label);

        return $this->paginator->paginate(
            $queryBuilder,
            $page,
            $limit,
            [
                'defaultSortFieldName' => 'j.createdAt',
                'defaultSortDirection' => 'desc',
            ]
        );
    }

    /**
     * Get available domain URLs from journal.
     *
     * @return string[]
     */
    public function getAvailableDomains(): array
    {
        // Join with ApplicationInstallation and then Bitrix24Account to get domain URLs
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('DISTINCT b24.domainUrl')
            ->from(JournalItem::class, 'j')
            ->innerJoin(ApplicationInstallation::class, 'ai', 'WITH', 'ai.id = j.applicationInstallationId')
            ->innerJoin(Bitrix24Account::class, 'b24', 'WITH', 'b24.id = ai.bitrix24AccountId')
            ->orderBy('b24.domainUrl', 'ASC')
        ;

        $results = $queryBuilder->getQuery()->getScalarResult();

        return array_column($results, 'domainUrl');
    }

    /**
     * Get available labels from journal.
     *
     * @return string[]
     */
    public function getAvailableLabels(): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('DISTINCT j.label')
            ->from(JournalItem::class, 'j')
            ->where('j.label IS NOT NULL')
            ->orderBy('j.label', 'ASC')
        ;

        $results = $queryBuilder->getQuery()->getScalarResult();

        return array_filter(array_column($results, 'label'));
    }

    /**
     * Create query builder with filters.
     */
    private function createFilteredQueryBuilder(
        ?string $memberId = null,
        ?Domain $domain = null,
        ?string $logLevel = null,
        ?string $label = null
    ): QueryBuilder {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('j')
            ->from(JournalItem::class, 'j')
        ;

        if (null !== $memberId) {
            $queryBuilder->andWhere('j.memberId = :memberId')
                ->setParameter('memberId', $memberId)
            ;
        }

        if (null !== $domain) {
            $queryBuilder->innerJoin(ApplicationInstallation::class, 'ai', 'WITH', 'ai.id = j.applicationInstallationId')
                ->innerJoin(Bitrix24Account::class, 'b24', 'WITH', 'b24.id = ai.bitrix24AccountId')
                ->andWhere('b24.domainUrl = :domainUrl')
                ->setParameter('domainUrl', $domain->value)
            ;
        }

        if (null !== $logLevel) {
            $queryBuilder->andWhere('j.level = :level')
                ->setParameter('level', $logLevel)
            ;
        }

        if (null !== $label) {
            $queryBuilder->andWhere('j.label = :label')
                ->setParameter('label', $label)
            ;
        }

        $queryBuilder->orderBy('j.createdAt', 'DESC');

        return $queryBuilder;
    }
}
