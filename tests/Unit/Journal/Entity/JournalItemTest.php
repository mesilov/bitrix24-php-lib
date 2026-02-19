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

namespace Bitrix24\Lib\Tests\Unit\Journal\Entity;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class JournalItemTest extends TestCase
{
    private Uuid $applicationInstallationId;

    private string $memberId;

    #[\Override]
    protected function setUp(): void
    {
        $this->applicationInstallationId = Uuid::v7();
        $this->memberId = 'test-member-id';
    }

    public function testCreateJournalItemWithInfoLevel(): void
    {
        $message = 'Test info message';
        $label = 'test.label';
        $userName = 'test-user';
        $journalContext = new Context(
            payload: ['key' => 'value'],
            bitrix24UserId: 123
        );

        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, $message, $label, $userName, $journalContext);

        $this->assertInstanceOf(JournalItem::class, $journalItem);
        $this->assertSame(LogLevel::info, $journalItem->getLevel());
        $this->assertSame($this->memberId, $journalItem->getMemberId());
        $this->assertSame($message, $journalItem->getMessage());
        $this->assertSame($label, $journalItem->getLabel());
        $this->assertSame($userName, $journalItem->getUserId());
        $this->assertTrue($journalItem->getApplicationInstallationId()->equals($this->applicationInstallationId));
        $this->assertSame(['key' => 'value'], $journalItem->getContext()->getPayload());
        $this->assertSame(123, $journalItem->getContext()->getBitrix24UserId());
    }

    public function testCreateJournalItemWithEmergencyLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::emergency($this->memberId, $this->applicationInstallationId, 'Emergency message', 'emergency.label', null, $journalContext);

        $this->assertSame(LogLevel::emergency, $journalItem->getLevel());
        $this->assertSame('Emergency message', $journalItem->getMessage());
    }

    public function testCreateJournalItemWithAlertLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::alert($this->memberId, $this->applicationInstallationId, 'Alert message', 'alert.label', null, $journalContext);

        $this->assertSame(LogLevel::alert, $journalItem->getLevel());
    }

    public function testCreateJournalItemWithCriticalLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::critical($this->memberId, $this->applicationInstallationId, 'Critical message', 'critical.label', null, $journalContext);

        $this->assertSame(LogLevel::critical, $journalItem->getLevel());
    }

    public function testCreateJournalItemWithErrorLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::error($this->memberId, $this->applicationInstallationId, 'Error message', 'error.label', null, $journalContext);

        $this->assertSame(LogLevel::error, $journalItem->getLevel());
    }

    public function testCreateJournalItemWithWarningLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::warning($this->memberId, $this->applicationInstallationId, 'Warning message', 'warning.label', null, $journalContext);

        $this->assertSame(LogLevel::warning, $journalItem->getLevel());
    }

    public function testCreateJournalItemWithNoticeLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::notice($this->memberId, $this->applicationInstallationId, 'Notice message', 'notice.label', null, $journalContext);

        $this->assertSame(LogLevel::notice, $journalItem->getLevel());
    }

    public function testCreateJournalItemWithDebugLevel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::debug($this->memberId, $this->applicationInstallationId, 'Debug message', 'debug.label', null, $journalContext);

        $this->assertSame(LogLevel::debug, $journalItem->getLevel());
    }

    public function testJournalItemHasUniqueId(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 1', 'test.label', null, $journalContext);
        $item2 = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Message 2', 'test.label', null, $journalContext);

        $this->assertNotEquals($journalItem->getId()->toRfc4122(), $item2->getId()->toRfc4122());
    }

    public function testJournalItemHasCreatedAt(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Test message', 'test.label', null, $journalContext);

        $this->assertNotNull($journalItem->getCreatedAt());
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $journalItem->getCreatedAt());
    }

    public function testCreateJournalItemWithEmptyMessageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal message cannot be empty');

        $journalContext = new Context();
        JournalItem::info($this->memberId, $this->applicationInstallationId, '', 'test.label', null, $journalContext);
    }

    public function testCreateJournalItemWithWhitespaceMessageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal message cannot be empty');

        $journalContext = new Context();
        JournalItem::info($this->memberId, $this->applicationInstallationId, '   ', 'test.label', null, $journalContext);
    }

    public function testCreateJournalItemWithEmptyMemberIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('memberId cannot be empty');

        $journalContext = new Context();
        JournalItem::info('', $this->applicationInstallationId, 'Message', 'test.label', null, $journalContext);
    }

    public function testJournalItemContextWithoutLabel(): void
    {
        $journalContext = new Context();
        $journalItem = JournalItem::info($this->memberId, $this->applicationInstallationId, 'Test message', 'test.label', null, $journalContext);

        $this->assertSame('test.label', $journalItem->getLabel());
        $this->assertNull($journalItem->getContext()->getPayload());
        $this->assertNull($journalItem->getContext()->getBitrix24UserId());
        $this->assertNull($journalItem->getContext()->getIpAddress());
    }

    public function testJournalItemWithComplexPayload(): void
    {
        $payload = [
            'action' => 'sync',
            'items' => 150,
            'nested' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];

        $journalContext = new Context(payload: $payload);
        $journalItem = JournalItem::info(
            $this->memberId,
            $this->applicationInstallationId,
            'Sync completed',
            'sync.label',
            null,
            $journalContext
        );

        $this->assertSame($payload, $journalItem->getContext()->getPayload());
    }
}
