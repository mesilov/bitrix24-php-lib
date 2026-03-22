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
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Tests\Unit\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
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
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Test message', 'test.label', null, $journalContext);

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
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message 1', 'test.label', null, $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::ERROR, 'Message 2', 'test.label', null, $journalContext);
        $item3 = new JournalItem('other-member', Uuid::v7(), LogLevel::INFO, 'Message 3', 'test.label', null, $journalContext); // Different installation

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByApplicationInstallationId($this->memberId, $this->applicationInstallationId);

        $this->assertCount(2, $items);
    }

    public function testFindByApplicationInstallationIdWithLevelFilter(): void
    {
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message 1', 'test.label', null, $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::ERROR, 'Message 2', 'test.label', null, $journalContext);
        $item3 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message 3', 'test.label', null, $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $items = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            logLevel: LogLevel::INFO
        );

        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(LogLevel::INFO, $item->getLevel());
        }
    }

    public function testFindByMemberId(): void
    {
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message 1', 'test.label', null, $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::ERROR, 'Message 2', 'test.label', null, $journalContext);
        $item3 = new JournalItem('other-member', Uuid::v7(), LogLevel::INFO, 'Message 3', 'test.label', null, $journalContext);

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
        $journalContext = new Context(['key' => 'value']);
        for ($i = 1; $i <= 5; ++$i) {
            $item = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message '.$i, 'test.label', null, $journalContext);
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
        $journalContext = new Context(['key' => 'value']);
        for ($i = 1; $i <= 5; ++$i) {
            $item = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message '.$i, 'test.label', null, $journalContext);
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
        $journalContext = new Context(['key' => 'value']);
        for ($i = 1; $i <= 10; ++$i) {
            $item = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message '.$i, 'test.label', null, $journalContext);
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
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message', 'test.label', null, $journalContext);
        $this->repository->save($journalItem);

        $futureDate = new CarbonImmutable('+1 day');
        $deleted = $this->repository->deleteOlderThan($this->memberId, $this->applicationInstallationId, $futureDate);

        // Item should be deleted as it's older than future date
        $this->assertSame(1, $deleted);
    }

    public function testClear(): void
    {
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message', 'test.label', null, $journalContext);
        $this->repository->save($journalItem);

        $this->assertNotEmpty($this->repository->findAll());

        $this->repository->clear();

        $this->assertEmpty($this->repository->findAll());
    }

    public function testFindAll(): void
    {
        $journalContext = new Context(['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::INFO, 'Message 1', 'test.label', null, $journalContext);
        $item2 = new JournalItem('other-member', Uuid::v7(), LogLevel::ERROR, 'Message 2', 'test.label', null, $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);

        $all = $this->repository->findAll();

        $this->assertCount(2, $all);
    }
}
