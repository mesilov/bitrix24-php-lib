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
use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
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

    /**
     * @throws NewsNotFoundException
     */
    #[\Override]
    public function getById(Uuid $uuid): NewsInterface
    {
        $news = $this->repository
            ->createQueryBuilder('b24')
            ->where('b24.id = :id')
            ->andWhere('b24.status != :status')
            ->setParameter('id', $uuid)
            ->setParameter('status', NewsStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $news) {
            throw new NewsNotFoundException(
                sprintf('news not found by id %s', $uuid->toRfc4122())
            );
        }

        return $news;
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
