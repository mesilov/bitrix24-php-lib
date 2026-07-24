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

use Bitrix24\Lib\News\Entity\NewsInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Interface for News repository.
 */
interface NewsRepositoryInterface
{
    /**
     * Save news.
     */
    public function save(NewsInterface $news): void;

    /**
     * Get news by ID (any status).
     */
    public function getById(Uuid $uuid): NewsInterface;

    /**
     * Find published news for the in-app feed (newest first), paginated.
     *
     * @return PaginationInterface<NewsInterface>
     */
    public function findPublished(int $page = 1, int $limit = 20): PaginationInterface;
}
