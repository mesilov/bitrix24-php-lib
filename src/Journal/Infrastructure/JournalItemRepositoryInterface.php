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

namespace Bitrix24\Lib\Journal\Infrastructure;

use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Journal item repository interface for SDK contract extraction.
 */
interface JournalItemRepositoryInterface
{
    /**
     * Save journal item.
     */
    public function save(JournalItemInterface $journalItem): void;

    /**
     * Get journal item by ID.
     */
    public function getById(Uuid $uuid): JournalItemInterface;

    /**
     * Find journal item by ID.
     */
    public function findById(Uuid $uuid): ?JournalItemInterface;

    /**
     * Find journal items by application installation ID.
     *
     * @return JournalItemInterface[]
     */
    public function findByApplicationInstallationId(
        string $memberId,
        Uuid $applicationInstallationId,
        ?string $logLevel = null,
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * Find journal items by member ID.
     *
     * @return JournalItemInterface[]
     */
    public function findByMemberId(
        string $memberId,
        ?string $logLevel = null,
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * Delete journal items older than specified date.
     */
    public function deleteOlderThan(
        string $memberId,
        Uuid $applicationInstallationId,
        CarbonImmutable $date
    ): int;
}
