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

namespace Bitrix24\Lib\News\Infrastructure\Doctrine;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Entity\NewsInterface;
use Bitrix24\Lib\News\Entity\NewsStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class NewsRepository implements NewsRepositoryInterface
{
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(News::class);
    }

    #[\Override]
    public function save(NewsInterface $news): void
    {
        $this->entityManager->persist($news);
    }

    #[\Override]
    public function findById(Uuid $uuid): ?NewsInterface
    {
        return $this->repository
            ->createQueryBuilder('news')
            ->where('news.id = :id')
            ->andWhere('news.status != :deletedStatus')
            ->setParameter('id', $uuid)
            ->setParameter('deletedStatus', NewsStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findPublished(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);

        return $this->repository
            ->createQueryBuilder('news')
            ->where('news.status = :status')
            ->setParameter('status', NewsStatus::published)
            ->orderBy('news.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult()
        ;
    }
}
