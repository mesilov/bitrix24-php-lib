<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Create;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Create\Command;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        string $title,
        ?string $site,
        ?string $email,
        ?int $bitrix24PartnerId,
        ?string $openLineId,
        ?string $externalId,
        ?string $expectedException,
        ?string $expectedExceptionMessage
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        new Command(
            $title,
            $site,
            null, // phone
            $email,
            $bitrix24PartnerId,
            $openLineId,
            $externalId
        );
    }

    public static function dataForCommand(): \Generator
    {
        yield 'validCommand' => [
            'Test Partner',
            'https://example.com',
            'test@example.com',
            123,
            'line-123',
            'ext-123',
            null,
            null,
        ];

        yield 'emptyTitle' => [
            '',
            'https://example.com',
            'test@example.com',
            123,
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'title must be a non-empty string',
        ];

        yield 'emptySite' => [
            'Test Partner',
            '',
            'test@example.com',
            123,
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'site must be null or non-empty string',
        ];

        yield 'emptyEmail' => [
            'Test Partner',
            'https://example.com',
            '',
            123,
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'email must be null or non-empty string',
        ];

        yield 'negativeBitrix24PartnerId' => [
            'Test Partner',
            'https://example.com',
            'test@example.com',
            -1,
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'bitrix24PartnerId must be null or non-negative integer',
        ];

        yield 'emptyOpenLineId' => [
            'Test Partner',
            'https://example.com',
            'test@example.com',
            123,
            '',
            'ext-123',
            \InvalidArgumentException::class,
            'openLineId must be null or non-empty string',
        ];

        yield 'emptyExternalId' => [
            'Test Partner',
            'https://example.com',
            'test@example.com',
            123,
            'line-123',
            '',
            \InvalidArgumentException::class,
            'externalId must be null or non-empty string',
        ];
    }
}
