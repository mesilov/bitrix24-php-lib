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
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class JournalItemTest extends TestCase
{
    private Uuid $applicationInstallationId;

    protected function setUp(): void
    {
        $this->applicationInstallationId = Uuid::v7();
    }

    public function testCreateJournalItemWithInfoLevel(): void
    {
        $message = 'Test info message';
        $context = [
            'label' => 'test.label',
            'payload' => ['key' => 'value'],
            'bitrix24UserId' => 123,
            'ipAddress' => '192.168.1.1',
        ];

        $item = JournalItem::info($this->applicationInstallationId, $message, $context);

        $this->assertInstanceOf(JournalItem::class, $item);
        $this->assertSame(LogLevel::info, $item->getLevel());
        $this->assertSame($message, $item->getMessage());
        $this->assertTrue($item->getApplicationInstallationId()->equals($this->applicationInstallationId));
        $this->assertSame('test.label', $item->getContext()->getLabel());
        $this->assertSame(['key' => 'value'], $item->getContext()->getPayload());
        $this->assertSame(123, $item->getContext()->getBitrix24UserId());
    }

    public function testCreateJournalItemWithEmergencyLevel(): void
    {
        $item = JournalItem::emergency($this->applicationInstallationId, 'Emergency message');

        $this->assertSame(LogLevel::emergency, $item->getLevel());
        $this->assertSame('Emergency message', $item->getMessage());
    }

    public function testCreateJournalItemWithAlertLevel(): void
    {
        $item = JournalItem::alert($this->applicationInstallationId, 'Alert message');

        $this->assertSame(LogLevel::alert, $item->getLevel());
    }

    public function testCreateJournalItemWithCriticalLevel(): void
    {
        $item = JournalItem::critical($this->applicationInstallationId, 'Critical message');

        $this->assertSame(LogLevel::critical, $item->getLevel());
    }

    public function testCreateJournalItemWithErrorLevel(): void
    {
        $item = JournalItem::error($this->applicationInstallationId, 'Error message');

        $this->assertSame(LogLevel::error, $item->getLevel());
    }

    public function testCreateJournalItemWithWarningLevel(): void
    {
        $item = JournalItem::warning($this->applicationInstallationId, 'Warning message');

        $this->assertSame(LogLevel::warning, $item->getLevel());
    }

    public function testCreateJournalItemWithNoticeLevel(): void
    {
        $item = JournalItem::notice($this->applicationInstallationId, 'Notice message');

        $this->assertSame(LogLevel::notice, $item->getLevel());
    }

    public function testCreateJournalItemWithDebugLevel(): void
    {
        $item = JournalItem::debug($this->applicationInstallationId, 'Debug message');

        $this->assertSame(LogLevel::debug, $item->getLevel());
    }

    public function testJournalItemHasUniqueId(): void
    {
        $item1 = JournalItem::info($this->applicationInstallationId, 'Message 1');
        $item2 = JournalItem::info($this->applicationInstallationId, 'Message 2');

        $this->assertNotEquals($item1->getId()->toRfc4122(), $item2->getId()->toRfc4122());
    }

    public function testJournalItemHasCreatedAt(): void
    {
        $item = JournalItem::info($this->applicationInstallationId, 'Test message');

        $this->assertNotNull($item->getCreatedAt());
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $item->getCreatedAt());
    }

    public function testCreateJournalItemWithEmptyMessageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal message cannot be empty');

        JournalItem::info($this->applicationInstallationId, '');
    }

    public function testCreateJournalItemWithWhitespaceMessageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal message cannot be empty');

        JournalItem::info($this->applicationInstallationId, '   ');
    }

    public function testJournalItemContextWithoutOptionalFields(): void
    {
        $item = JournalItem::info($this->applicationInstallationId, 'Test message');

        $this->assertNull($item->getContext()->getLabel());
        $this->assertNull($item->getContext()->getPayload());
        $this->assertNull($item->getContext()->getBitrix24UserId());
        $this->assertNull($item->getContext()->getIpAddress());
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

        $item = JournalItem::info(
            $this->applicationInstallationId,
            'Sync completed',
            ['payload' => $payload]
        );

        $this->assertSame($payload, $item->getContext()->getPayload());
    }
}
