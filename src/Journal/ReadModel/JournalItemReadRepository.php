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

namespace Bitrix24\Lib\Journal\ReadModel;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Read model repository for journal items with filtering and pagination.
 */
readonly class JournalItemReadRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaginatorInterface $paginator
    ) {}

    /**
     * Find journal items with filters and pagination.
     *
     * @return PaginationInterface<JournalItemInterface>
     */
    public function findWithFilters(
        ?string $domainUrl = null,
        ?LogLevel $logLevel = null,
        ?string $label = null,
        int $page = 1,
        int $limit = 50
    ): PaginationInterface {
        $queryBuilder = $this->createFilteredQueryBuilder($domainUrl, $logLevel, $label);

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
     * Find journal item by ID.
     */
    public function findById(Uuid $uuid): ?JournalItemInterface
    {
        return $this->entityManager->getRepository(JournalItem::class)->find($uuid);
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
        $queryBuilder->select('DISTINCT j.context.label')
            ->from(JournalItem::class, 'j')
            ->where('j.context.label IS NOT NULL')
            ->orderBy('j.context.label', 'ASC')
        ;

        $results = $queryBuilder->getQuery()->getScalarResult();

        return array_filter(array_column($results, 'label'));
    }

    /**
     * Create query builder with filters.
     */
    private function createFilteredQueryBuilder(
        ?string $domainUrl = null,
        ?LogLevel $logLevel = null,
        ?string $label = null
    ): QueryBuilder {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('j')
            ->from(JournalItem::class, 'j')
        ;

        if (null !== $domainUrl && '' !== $domainUrl && '0' !== $domainUrl) {
            $queryBuilder->innerJoin(ApplicationInstallation::class, 'ai', 'WITH', 'ai.id = j.applicationInstallationId')
                ->innerJoin(Bitrix24Account::class, 'b24', 'WITH', 'b24.id = ai.bitrix24AccountId')
                ->andWhere('b24.domainUrl = :domainUrl')
                ->setParameter('domainUrl', $domainUrl)
            ;
        }

        if (null !== $logLevel) {
            $queryBuilder->andWhere('j.level = :level')
                ->setParameter('level', $logLevel)
            ;
        }

        if (null !== $label && '' !== $label && '0' !== $label) {
            $queryBuilder->andWhere('j.context.label = :label')
                ->setParameter('label', $label)
            ;
        }

        $queryBuilder->orderBy('j.createdAt', 'DESC');

        return $queryBuilder;
    }
}
