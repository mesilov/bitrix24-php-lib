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

namespace Bitrix24\Lib\Tests\Unit\Journal\Infrastructure;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class InMemoryJournalItemRepositoryTest extends TestCase
{
    private InMemoryJournalItemRepository $repository;

    private Uuid $applicationInstallationId;

    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $this->applicationInstallationId = Uuid::v7();
    }

    public function testSaveAndFindById(): void
    {
        $item = JournalItem::info($this->applicationInstallationId, 'Test message');

        $this->repository->save($item);

        $found = $this->repository->findById($item->getId());

        $this->assertNotNull($found);
        $this->assertSame($item->getId()->toRfc4122(), $found->getId()->toRfc4122());
        $this->assertSame($item->getMessage(), $found->getMessage());
    }

    public function testFindByIdReturnsNullForNonexistent(): void
    {
        $found = $this->repository->findById(Uuid::v7());

        $this->assertNull($found);
    }

    public function testFindByApplicationInstallationId(): void
    {
        $item1 = JournalItem::info($this->applicationInstallationId, 'Message 1');
        $item2 = JournalItem::error($this->applicationInstallationId, 'Message 2');
        $item3 = JournalItem::info(Uuid::v7(), 'Message 3'); // Different installation

        $this->repository->save($item1);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByApplicationInstallationId($this->applicationInstallationId);

        $this->assertCount(2, $items);
    }

    public function testFindByApplicationInstallationIdWithLevelFilter(): void
    {
        $item1 = JournalItem::info($this->applicationInstallationId, 'Message 1');
        $item2 = JournalItem::error($this->applicationInstallationId, 'Message 2');
        $item3 = JournalItem::info($this->applicationInstallationId, 'Message 3');

        $this->repository->save($item1);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByApplicationInstallationId(
            $this->applicationInstallationId,
            LogLevel::info
        );

        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(LogLevel::info, $item->getLevel());
        }
    }

    public function testFindByApplicationInstallationIdWithLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $item = JournalItem::info($this->applicationInstallationId, "Message {$i}");
            $this->repository->save($item);
        }

        $items = $this->repository->findByApplicationInstallationId(
            $this->applicationInstallationId,
            limit: 3
        );

        $this->assertCount(3, $items);
    }

    public function testFindByApplicationInstallationIdWithOffset(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $item = JournalItem::info($this->applicationInstallationId, "Message {$i}");
            $this->repository->save($item);
        }

        $items = $this->repository->findByApplicationInstallationId(
            $this->applicationInstallationId,
            offset: 2
        );

        $this->assertCount(3, $items);
    }

    public function testFindByApplicationInstallationIdWithLimitAndOffset(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $item = JournalItem::info($this->applicationInstallationId, "Message {$i}");
            $this->repository->save($item);
        }

        $items = $this->repository->findByApplicationInstallationId(
            $this->applicationInstallationId,
            limit: 3,
            offset: 2
        );

        $this->assertCount(3, $items);
    }

    public function testDeleteByApplicationInstallationId(): void
    {
        $item1 = JournalItem::info($this->applicationInstallationId, 'Message 1');
        $item2 = JournalItem::info($this->applicationInstallationId, 'Message 2');
        $otherInstallationId = Uuid::v7();
        $item3 = JournalItem::info($otherInstallationId, 'Message 3');

        $this->repository->save($item1);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $deleted = $this->repository->deleteByApplicationInstallationId($this->applicationInstallationId);

        $this->assertSame(2, $deleted);
        $this->assertEmpty($this->repository->findByApplicationInstallationId($this->applicationInstallationId));
        $this->assertCount(1, $this->repository->findByApplicationInstallationId($otherInstallationId));
    }

    public function testDeleteOlderThan(): void
    {
        // We can't easily test this with real timestamps in unit tests
        // This test verifies the method exists and doesn't crash
        $item = JournalItem::info($this->applicationInstallationId, 'Message');
        $this->repository->save($item);

        $futureDate = new CarbonImmutable('+1 day');
        $deleted = $this->repository->deleteOlderThan($futureDate);

        // Item should be deleted as it's older than future date
        $this->assertSame(1, $deleted);
    }

    public function testCountByApplicationInstallationId(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $item = JournalItem::info($this->applicationInstallationId, "Message {$i}");
            $this->repository->save($item);
        }

        $count = $this->repository->countByApplicationInstallationId($this->applicationInstallationId);

        $this->assertSame(5, $count);
    }

    public function testCountByApplicationInstallationIdWithLevelFilter(): void
    {
        $this->repository->save(JournalItem::info($this->applicationInstallationId, 'Info 1'));
        $this->repository->save(JournalItem::info($this->applicationInstallationId, 'Info 2'));
        $this->repository->save(JournalItem::error($this->applicationInstallationId, 'Error 1'));

        $count = $this->repository->countByApplicationInstallationId(
            $this->applicationInstallationId,
            LogLevel::info
        );

        $this->assertSame(2, $count);
    }

    public function testClear(): void
    {
        $item = JournalItem::info($this->applicationInstallationId, 'Message');
        $this->repository->save($item);

        $this->assertNotEmpty($this->repository->findAll());

        $this->repository->clear();

        $this->assertEmpty($this->repository->findAll());
    }

    public function testFindAll(): void
    {
        $item1 = JournalItem::info($this->applicationInstallationId, 'Message 1');
        $item2 = JournalItem::error(Uuid::v7(), 'Message 2');

        $this->repository->save($item1);
        $this->repository->save($item2);

        $all = $this->repository->findAll();

        $this->assertCount(2, $all);
    }
}
