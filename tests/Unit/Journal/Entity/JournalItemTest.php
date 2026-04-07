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

use Darsyn\IP\Version\Multi as IP;
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

    private IP $ip;

    #[\Override]
    protected function setUp(): void
    {
        $this->applicationInstallationId = Uuid::v7();
        $this->memberId = 'test-member-id';
        $this->ip = IP::factory('127.0.0.1');
    }

    public function testCreateJournalItemWithInfoLevel(): void
    {
        $message = 'Test info message';
        $label = 'test.label';
        $journalContext = new Context(
            ipAddress: $this->ip,
            payload: ['key' => 'value'],
            bitrix24UserId: 123
        );

        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, $message, $label, $journalContext);

        $this->assertInstanceOf(JournalItem::class, $journalItem);
        $this->assertSame(LogLevel::info, $journalItem->getLevel());
        $this->assertSame($this->memberId, $journalItem->getMemberId());
        $this->assertSame($message, $journalItem->getMessage());
        $this->assertSame($label, $journalItem->getLabel());
        $this->assertTrue($journalItem->getApplicationInstallationId()->equals($this->applicationInstallationId));
        $this->assertSame(['key' => 'value'], $journalItem->getContext()->getPayload());
        $this->assertSame(123, $journalItem->getContext()->getBitrix24UserId());
    }

    public function testJournalItemHasUniqueId(): void
    {
        $journalContext = new Context($this->ip);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 1', 'test.label', $journalContext);
        $item2 = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Message 2', 'test.label', $journalContext);

        $this->assertNotEquals($journalItem->getId()->toRfc4122(), $item2->getId()->toRfc4122());
    }

    public function testJournalItemHasCreatedAt(): void
    {
        $journalContext = new Context($this->ip);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Test message', 'test.label', $journalContext);

        $this->assertNotNull($journalItem->getCreatedAt());
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $journalItem->getCreatedAt());
    }

    public function testCreateJournalItemWithEmptyMessageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal message cannot be empty');

        $journalContext = new Context($this->ip);
        new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, '', 'test.label', $journalContext);
    }

    public function testCreateJournalItemWithWhitespaceMessageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal message cannot be empty');

        $journalContext = new Context($this->ip);
        new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, '   ', 'test.label', $journalContext);
    }

    public function testCreateJournalItemWithEmptyMemberIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('memberId cannot be empty');

        $journalContext = new Context($this->ip);
        new JournalItem('', $this->applicationInstallationId, LogLevel::info, 'Message', 'test.label', $journalContext);
    }

    public function testJournalItemContextWithoutLabel(): void
    {
        $journalContext = new Context($this->ip);
        $journalItem = new JournalItem($this->memberId, $this->applicationInstallationId, LogLevel::info, 'Test message', 'test.label', $journalContext);

        $this->assertSame('test.label', $journalItem->getLabel());
        $this->assertNull($journalItem->getContext()->getPayload());
        $this->assertNull($journalItem->getContext()->getBitrix24UserId());
        $this->assertSame($this->ip->getCompactedAddress(), $journalItem->getContext()->getIpAddress()->getCompactedAddress());
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

        $journalContext = new Context($this->ip, payload: $payload);
        $journalItem = new JournalItem(
            $this->memberId,
            $this->applicationInstallationId,
            LogLevel::info,
            'Sync completed',
            'sync.label',
            $journalContext
        );

        $this->assertSame($payload, $journalItem->getContext()->getPayload());
    }
}
