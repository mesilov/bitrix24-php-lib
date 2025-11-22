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

namespace Bitrix24\Lib\Journal\Infrastructure\InMemory;

use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory implementation of JournalItemRepository for testing
 */
class InMemoryJournalItemRepository implements JournalItemRepositoryInterface
{
    /**
     * @var array<string, JournalItemInterface>
     */
    private array $items = [];

    #[\Override]
    public function save(JournalItemInterface $journalItem): void
    {
        $this->items[$journalItem->getId()->toRfc4122()] = $journalItem;
    }

    #[\Override]
    public function findById(Uuid $id): ?JournalItemInterface
    {
        return $this->items[$id->toRfc4122()] ?? null;
    }

    /**
     * @return JournalItemInterface[]
     */
    #[\Override]
    public function findByApplicationInstallationId(
        Uuid $applicationInstallationId,
        ?LogLevel $level = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $filtered = array_filter(
            $this->items,
            static function (JournalItemInterface $item) use ($applicationInstallationId, $level): bool {
                if (!$item->getApplicationInstallationId()->equals($applicationInstallationId)) {
                    return false;
                }

                if ($level !== null && $item->getLevel() !== $level) {
                    return false;
                }

                return true;
            }
        );

        // Sort by created date descending
        usort($filtered, static function (JournalItemInterface $a, JournalItemInterface $b): int {
            return $b->getCreatedAt()->getTimestamp() <=> $a->getCreatedAt()->getTimestamp();
        });

        if ($offset !== null) {
            $filtered = array_slice($filtered, $offset);
        }

        if ($limit !== null) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    #[\Override]
    public function deleteByApplicationInstallationId(Uuid $applicationInstallationId): int
    {
        $count = 0;
        foreach ($this->items as $key => $item) {
            if ($item->getApplicationInstallationId()->equals($applicationInstallationId)) {
                unset($this->items[$key]);
                ++$count;
            }
        }

        return $count;
    }

    #[\Override]
    public function deleteOlderThan(CarbonImmutable $date): int
    {
        $count = 0;
        foreach ($this->items as $key => $item) {
            if ($item->getCreatedAt()->isBefore($date)) {
                unset($this->items[$key]);
                ++$count;
            }
        }

        return $count;
    }

    #[\Override]
    public function countByApplicationInstallationId(Uuid $applicationInstallationId, ?LogLevel $level = null): int
    {
        return count($this->findByApplicationInstallationId($applicationInstallationId, $level));
    }

    /**
     * Get all items (for testing purposes)
     *
     * @return JournalItemInterface[]
     */
    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * Clear all items (for testing purposes)
     */
    public function clear(): void
    {
        $this->items = [];
    }
}
