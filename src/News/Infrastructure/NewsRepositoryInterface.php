<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\Infrastructure;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\Uid\Uuid;

interface NewsRepositoryInterface
{
    /**
     * Save news.
     */
    public function save(News $newsItem): void;

    /**
     * Get news by ID (excludes deleted).
     *
     * @throws NewsNotFoundException
     */
    public function getById(Uuid $uuid): News;

    /**
     * Find news by exact title match.
     *
     * @return News[]
     */
    public function findByTitle(string $title): array;

    /**
     * Find published news for the in-app feed (newest first), paginated.
     *
     * @return PaginationInterface<News>
     */
    public function findPublished(int $page = 1, int $limit = 20): PaginationInterface;

    /**
     * Find draft news for the admin panel (newest first), paginated.
     *
     * @return PaginationInterface<News>
     */
    public function findDrafts(int $page = 1, int $limit = 20): PaginationInterface;
}
