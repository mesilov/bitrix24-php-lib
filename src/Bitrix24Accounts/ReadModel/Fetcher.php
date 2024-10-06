<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\ReadModel;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class Fetcher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface     $paginator)
    {
    }

    public function list(
        int $page,
        int $size
    ): PaginationInterface
    {
        $queryBuilder = $this->em->getConnection()->createQueryBuilder()
            ->select(
                'b24account.id as id',
                'b24account.status as status',
                'b24account.member_id as member_id',
                'b24account.domain_url as domain_url',
                'b24account.app_version as application_version',
                'b24account.created_at_utc as created_at',
                'b24account.updated_at_utc as updated_at',
            )
            ->from('bitrix24account', 'b24account')
            ->orderBy('b24account.created_at_utc', 'DESC');

        return $this->paginator->paginate($queryBuilder, $page, $size);
    }
}