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
use Bitrix24\Lib\Journal\ValueObjects\JournalContext;
use Bitrix24\Lib\Tests\Unit\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class InMemoryJournalItemRepositoryTest extends TestCase
{
    private InMemoryJournalItemRepository $repository;

    private Uuid $applicationInstallationId;

    private string $memberId;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $this->applicationInstallationId = Uuid::v7();
        $this->memberId = 'test-member-id';
    }

    public function testSaveAndFindById(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Test message', $journalContext);

        $this->repository->save($journalItem);

        $found = $this->repository->findById($journalItem->getId());

        $this->assertNotNull($found);
        $this->assertSame($journalItem->getId()->toRfc4122(), $found->getId()->toRfc4122());
        $this->assertSame($journalItem->getMessage(), $found->getMessage());
    }

    public function testFindByIdReturnsNullForNonexistent(): void
    {
        $found = $this->repository->findById(Uuid::v7());

        $this->assertNull($found);
    }

    public function testFindByApplicationInstallationId(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 1', $journalContext);
        $item2 = JournalItem::error($this->memberId, $this->applicationInstallationId, 'Message 2', $journalContext);
        $item3 = JournalItem::info('other-member', Uuid::v7(), 'Message 3', $journalContext); // Different installation

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByApplicationInstallationId($this->memberId, $this->applicationInstallationId);

        $this->assertCount(2, $items);
    }

    public function testFindByApplicationInstallationIdWithLevelFilter(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 1', $journalContext);
        $item2 = JournalItem::error($this->memberId, $this->applicationInstallationId, 'Message 2', $journalContext);
        $item3 = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 3', $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            logLevel: LogLevel::info
        );

        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(LogLevel::info, $item->getLevel());
        }
    }

    public function testFindByMemberId(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 1', $journalContext);
        $item2 = JournalItem::error($this->memberId, $this->applicationInstallationId, 'Message 2', $journalContext);
        $item3 = JournalItem::info('other-member', Uuid::v7(), 'Message 3', $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByMemberId($this->memberId);

        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame($this->memberId, $item->getMemberId());
        }
    }

    public function testFindByApplicationInstallationIdWithLimit(): void
    {
        $journalContext = new JournalContext('test.label');
        for ($i = 1; $i <= 5; ++$i) {
            $item = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message ' . $i, $journalContext);
            $this->repository->save($item);
        }

        $items = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            limit: 3
        );

        $this->assertCount(3, $items);
    }

    public function testFindByApplicationInstallationIdWithOffset(): void
    {
        $journalContext = new JournalContext('test.label');
        for ($i = 1; $i <= 5; ++$i) {
            $item = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message ' . $i, $journalContext);
            $this->repository->save($item);
        }

        $items = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            offset: 2
        );

        $this->assertCount(3, $items);
    }

    public function testFindByApplicationInstallationIdWithLimitAndOffset(): void
    {
        $journalContext = new JournalContext('test.label');
        for ($i = 1; $i <= 10; ++$i) {
            $item = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message ' . $i, $journalContext);
            $this->repository->save($item);
        }

        $items = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            limit: 3,
            offset: 2
        );

        $this->assertCount(3, $items);
    }

    public function testDeleteOlderThan(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message', $journalContext);
        $this->repository->save($journalItem);

        $futureDate = new CarbonImmutable('+1 day');
        $deleted = $this->repository->deleteOlderThan($futureDate);

        // Item should be deleted as it's older than future date
        $this->assertSame(1, $deleted);
    }

    public function testClear(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message', $journalContext);
        $this->repository->save($journalItem);

        $this->assertNotEmpty($this->repository->findAll());

        $this->repository->clear();

        $this->assertEmpty($this->repository->findAll());
    }

    public function testFindAll(): void
    {
        $journalContext = new JournalContext('test.label');
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 1', $journalContext);
        $item2 = JournalItem::error('other-member', Uuid::v7(), 'Message 2', $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);

        $all = $this->repository->findAll();

        $this->assertCount(2, $all);
    }
}
