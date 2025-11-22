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
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Journal item repository interface for SDK contract extraction
 */
interface JournalItemRepositoryInterface
{
    /**
     * Save journal item
     */
    public function save(JournalItemInterface $journalItem): void;

    /**
     * Find journal item by ID
     */
    public function findById(Uuid $id): ?JournalItemInterface;

    /**
     * Find journal items by application installation ID
     *
     * @return JournalItemInterface[]
     */
    public function findByApplicationInstallationId(
        Uuid $applicationInstallationId,
        ?LogLevel $level = null,
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * Delete all journal items by application installation ID
     */
    public function deleteByApplicationInstallationId(Uuid $applicationInstallationId): int;

    /**
     * Delete journal items older than specified date
     */
    public function deleteOlderThan(CarbonImmutable $date): int;

    /**
     * Count journal items by application installation ID
     */
    public function countByApplicationInstallationId(Uuid $applicationInstallationId, ?LogLevel $level = null): int;
}
