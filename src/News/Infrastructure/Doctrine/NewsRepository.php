<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\Infrastructure\Doctrine;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Entity\NewsStatus;
use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
use Bitrix24\Lib\News\Infrastructure\NewsRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Uid\Uuid;

class NewsRepository implements NewsRepositoryInterface
{
    private readonly EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaginatorInterface $paginator
    ) {
        $this->repository = $this->entityManager->getRepository(News::class);
    }

    #[\Override]
    public function save(News $newsItem): void
    {
        $this->entityManager->persist($newsItem);
    }

    /**
     * @throws NewsNotFoundException
     */
    #[\Override]
    public function getById(Uuid $uuid): News
    {
        $newsItem = $this->repository
            ->createQueryBuilder('b24')
            ->where('b24.id = :id')
            ->andWhere('b24.status != :status')
            ->setParameter('id', $uuid)
            ->setParameter('status', NewsStatus::deleted)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $newsItem) {
            throw new NewsNotFoundException(
                sprintf('news not found by id %s', $uuid->toRfc4122())
            );
        }

        return $newsItem;
    }

    /**
     * @return News[]
     */
    #[\Override]
    public function findByTitle(string $title): array
    {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('news title cannot be empty');
        }

        return $this->repository->findBy(['title' => $title]);
    }

    /**
     * @return PaginationInterface<News>
     */
    #[\Override]
    public function findPublished(int $page = 1, int $limit = 20): PaginationInterface
    {
        $queryBuilder = $this->repository
            ->createQueryBuilder('news')
            ->where('news.status = :status')
            ->setParameter('status', NewsStatus::published)
        ;

        return $this->paginator->paginate(
            $queryBuilder,
            $page,
            $limit,
            [
                'defaultSortFieldName' => 'news.createdAt',
                'defaultSortDirection' => 'desc',
            ]
        );
    }

    /**
     * @return PaginationInterface<News>
     */
    #[\Override]
    public function findDrafts(int $page = 1, int $limit = 20): PaginationInterface
    {
        $queryBuilder = $this->repository
            ->createQueryBuilder('news')
            ->where('news.status = :status')
            ->setParameter('status', NewsStatus::draft)
        ;

        return $this->paginator->paginate(
            $queryBuilder,
            $page,
            $limit,
            [
                'defaultSortFieldName' => 'news.createdAt',
                'defaultSortDirection' => 'desc',
            ]
        );
    }
}
