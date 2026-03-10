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

namespace Bitrix24\Lib\Tests\Unit\Journal\Infrastructure\InMemory;

use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Carbon\CarbonImmutable;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory implementation of JournalItemRepository for testing.
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
    public function findById(Uuid $uuid): ?JournalItemInterface
    {
        return $this->items[$uuid->toRfc4122()] ?? null;
    }

    /**
     * @return JournalItemInterface[]
     */
    #[\Override]
    public function findByApplicationInstallationId(
        string $memberId,
        Uuid $applicationInstallationId,
        ?LogLevel $logLevel = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $filtered = array_filter(
            $this->items,
            static function (JournalItemInterface $journalItem) use ($applicationInstallationId, $memberId, $logLevel): bool {
                if ($journalItem->getMemberId() !== $memberId) {
                    return false;
                }

                if (!$journalItem->getApplicationInstallationId()->equals($applicationInstallationId)) {
                    return false;
                }

                if (null !== $logLevel && $journalItem->getLevel() !== $logLevel) {
                    return false;
                }

                return true;
            }
        );

        // Sort by created date descending
        usort($filtered, static fn (JournalItemInterface $a, JournalItemInterface $b): int => $b->getCreatedAt()->getTimestamp() <=> $a->getCreatedAt()->getTimestamp());

        if (null !== $offset) {
            $filtered = array_slice($filtered, $offset);
        }

        if (null !== $limit) {
            return array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    /**
     * @return JournalItemInterface[]
     */
    #[\Override]
    public function findByMemberId(
        string $memberId,
        ?LogLevel $logLevel = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $filtered = array_filter(
            $this->items,
            static function (JournalItemInterface $journalItem) use ($memberId, $logLevel): bool {
                if ($journalItem->getMemberId() !== $memberId) {
                    return false;
                }

                if (null !== $logLevel && $journalItem->getLevel() !== $logLevel) {
                    return false;
                }

                return true;
            }
        );

        // Sort by created date descending
        usort($filtered, static fn (JournalItemInterface $a, JournalItemInterface $b): int => $b->getCreatedAt()->getTimestamp() <=> $a->getCreatedAt()->getTimestamp());

        if (null !== $offset) {
            $filtered = array_slice($filtered, $offset);
        }

        if (null !== $limit) {
            return array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    #[\Override]
    public function deleteOlderThan(
        string $memberId,
        Uuid $applicationInstallationId,
        CarbonImmutable $date
    ): int {
        $count = 0;
        foreach ($this->items as $key => $item) {
            if ($item->getMemberId() === $memberId
                && $item->getApplicationInstallationId()->equals($applicationInstallationId)
                && $item->getCreatedAt()->isBefore($date)
            ) {
                unset($this->items[$key]);
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Get all items (for testing purposes).
     *
     * @return JournalItemInterface[]
     */
    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * Clear all items (for testing purposes).
     */
    public function clear(): void
    {
        $this->items = [];
    }
}
