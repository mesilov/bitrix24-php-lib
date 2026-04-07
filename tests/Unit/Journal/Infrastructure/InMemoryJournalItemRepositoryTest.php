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

use Darsyn\IP\Version\Multi as IP;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Tests\Unit\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
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

    private IP $ip;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $this->applicationInstallationId = Uuid::v7();
        $this->memberId = 'test-member-id';
        $this->ip = IP::factory('127.0.0.1');
    }

    public function testSaveAndFindById(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Test message', 'test.label', $journalContext);

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
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 1', 'test.label', $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::error, 'Message 2', 'test.label', $journalContext);
        $item3 = new JournalItem('other-member', Uuid::v7(), LogLevel::info, 'Message 3', 'test.label', $journalContext); // Different installation

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $pagination = $this->repository->findByApplicationInstallationId($this->memberId, $this->applicationInstallationId);

        $this->assertCount(2, $pagination);
    }

    public function testFindByApplicationInstallationIdWithLevelFilter(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 1', 'test.label', $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::error, 'Message 2', 'test.label', $journalContext);
        $item3 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 3', 'test.label', $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $pagination = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            logLevel: LogLevel::info
        );

        $this->assertCount(2, $pagination);
        foreach ($pagination as $item) {
            $this->assertSame(LogLevel::info, $item->getLevel());
        }
    }

    public function testFindByMemberId(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 1', 'test.label', $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::error, 'Message 2', 'test.label', $journalContext);
        $item3 = new JournalItem('other-member', Uuid::v7(), LogLevel::info, 'Message 3', 'test.label', $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);
        $this->repository->save($item3);

        $pagination = $this->repository->findByMemberId($this->memberId);

        $this->assertCount(2, $pagination);
        foreach ($pagination as $item) {
            $this->assertSame($this->memberId, $item->getMemberId());
        }
    }

    public function testFindByApplicationInstallationIdWithLimit(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        for ($i = 1; $i <= 5; ++$i) {
            $item = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message '.$i, 'test.label', $journalContext);
            $this->repository->save($item);
        }

        $pagination = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            limit: 3
        );

        $this->assertCount(3, $pagination);
    }

    public function testFindByApplicationInstallationIdWithPagination(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        for ($i = 1; $i <= 5; ++$i) {
            $item = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message '.$i, 'test.label', $journalContext);
            $this->repository->save($item);
        }

        $pagination = $this->repository->findByApplicationInstallationId(
            $this->memberId,
            $this->applicationInstallationId,
            page: 2,
            limit: 2
        );

        $this->assertCount(2, $pagination);
        $this->assertSame(5, $pagination->getTotalItemCount());
        $this->assertSame(2, $pagination->getCurrentPageNumber());
    }

    public function testDeleteOlderThan(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message', 'test.label', $journalContext);
        $this->repository->save($journalItem);

        $futureDate = new CarbonImmutable('+1 day');
        $deleted = $this->repository->deleteOlderThan($this->memberId, $this->applicationInstallationId, $futureDate);

        // Item should be deleted as it's older than future date
        $this->assertSame(1, $deleted);
    }

    public function testClear(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message', 'test.label', $journalContext);
        $this->repository->save($journalItem);

        $this->assertNotEmpty($this->repository->findAll());

        $this->repository->clear();

        $this->assertEmpty($this->repository->findAll());
    }

    public function testFindAll(): void
    {
        $journalContext = new Context($this->ip, ['key' => 'value']);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 1', 'test.label', $journalContext);
        $item2 = new JournalItem('other-member', Uuid::v7(), LogLevel::error, 'Message 2', 'test.label', $journalContext);

        $this->repository->save($journalItem);
        $this->repository->save($item2);

        $all = $this->repository->findAll();

        $this->assertCount(2, $all);
    }
}
