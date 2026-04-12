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
use Carbon\CarbonImmutable;
use Darsyn\IP\Version\Multi as IP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(JournalItem::class)]
class JournalItemTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForJournalItem')]
    public function testJournalItem(
        string   $memberId,
        Uuid     $applicationInstallationId,
        LogLevel $level,
        string   $message,
        string   $label,
        Context  $context,
        ?string  $expectedException = null
    ): void
    {
        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        $journalItem = new JournalItem(
            $memberId,
            $applicationInstallationId,
            $level,
            $message,
            $label,
            $context
        );

        if ($expectedException === null) {
            self::assertSame($memberId, $journalItem->getMemberId());
            self::assertTrue($applicationInstallationId->equals($journalItem->getApplicationInstallationId()));
            self::assertSame($level, $journalItem->getLevel());
            self::assertSame($message, $journalItem->getMessage());
            self::assertSame($label, $journalItem->getLabel());
            self::assertTrue($context->equals($journalItem->getContext()));
            self::assertInstanceOf(CarbonImmutable::class, $journalItem->getCreatedAt());
        }
    }

    public static function dataForJournalItem(): \Generator
    {
        $ip = IP::factory('127.0.0.1');
        $installId = Uuid::v7();
        $memberId = 'test-member-id';
        $context = new Context($ip, ['key' => 'value'], 123);

        yield 'successCreate' => [
            $memberId,
            $installId,
            LogLevel::info,
            'Test success create',
            'test.label',
            $context,
        ];

        yield 'successComplexPayload' => [
            $memberId,
            $installId,
            LogLevel::info,
            'Sync completed',
            'sync.label',
            new Context($ip, [
                'action' => 'sync',
                'items' => 150,
                'nested' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ]),
        ];

        yield 'emptyMessage' => [
            $memberId,
            $installId,
            LogLevel::info,
            '',
            'test.label',
            new Context($ip),
            InvalidArgumentException::class
        ];

        yield 'whitespaceMessage' => [
            $memberId,
            $installId,
            LogLevel::info,
            '   ',
            'test.label',
            new Context($ip),
            InvalidArgumentException::class
        ];

        yield 'emptyMemberId' => [
            '',
            $installId,
            LogLevel::info,
            'Message',
            'test.label',
            new Context($ip),
            InvalidArgumentException::class
        ];

        yield 'emptyLabel' => [
            $memberId,
            $installId,
            LogLevel::info,
            'Message',
            '',
            new Context($ip),
            InvalidArgumentException::class
        ];

        yield 'tooLongLabel' => [
            $memberId,
            $installId,
            LogLevel::info,
            'Message',
            str_repeat('a', 64),
            new Context($ip),
            InvalidArgumentException::class
        ];

        yield 'invalidLabelCharacters' => [
            $memberId,
            $installId,
            LogLevel::info,
            'Message',
            'invalid label!',
            new Context($ip),
            InvalidArgumentException::class,
        ];

        yield 'labelStartsWithDot' => [
            $memberId,
            $installId,
            LogLevel::info,
            'Message',
            '.label',
            new Context($ip),
            InvalidArgumentException::class
        ];
    }
}
