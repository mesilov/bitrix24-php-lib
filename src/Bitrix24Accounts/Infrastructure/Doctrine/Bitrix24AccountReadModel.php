<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Read-only access to Bitrix24 accounts for listing / reporting use cases.
 */
final readonly class Bitrix24AccountReadModel
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaginatorInterface $paginator
    ) {}

    /**
     * Returns all active Bitrix24 accounts with pagination and stable ordering by createdAt.
     *
     * @param string $sort  sort direction by account createdAt, 'asc' or 'desc'
     * @param int    $page  1-based page number
     * @param int    $limit number of items per page
     *
     * @return PaginationInterface<Bitrix24Account>
     *
     * @throws InvalidArgumentException if $sort is neither "asc" nor "desc"
     */
    public function findAllActive(string $sort = 'desc', int $page = 1, int $limit = 50): PaginationInterface
    {
        if ('asc' !== $sort && 'desc' !== $sort) {
            throw new InvalidArgumentException(
                sprintf('sort direction must be "asc" or "desc", got "%s"', $sort)
            );
        }

        $queryBuilder = $this->entityManager->getRepository(Bitrix24Account::class)
            ->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', Bitrix24AccountStatus::active->name)
            ->orderBy('a.createdAt', $sort)
        ;

        return $this->paginator->paginate(
            $queryBuilder,
            $page,
            $limit
        );
    }
}
