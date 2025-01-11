<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\ReadModel;

use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

class Bitrix24AccountFetcher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaginatorInterface $paginator
    ) {}

    public function list(
        int $page,
        int $size
    ): PaginationInterface {
        $qb = $this->em->createQueryBuilder()
            ->select(
                'b24account.id as id',
                'b24account.status as status',
                'b24account.memberId as member_id',
                'b24account.domainUrl as domain_url',
                'b24account.applicationVersion as application_version',
                'b24account.createdAt as created_at_utc',
                'b24account.updatedAt as updated_at_utc',
            )
            ->from('Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account', 'b24account')
            ->orderBy('b24account.createdAt', 'DESC');

        return $this->paginator->paginate($qb, $page, $size);
    }
}
