<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Upsert;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Upsert\Command;
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
        int $bitrix24PartnerNumber,
        ?string $site,
        ?string $email,
        ?string $openLineId,
        ?string $externalId,
        ?string $logoUrl,
        ?string $expectedException,
        ?string $expectedExceptionMessage
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $command = new Command(
            $title,
            $bitrix24PartnerNumber,
            $site,
            null,
            $email,
            $openLineId,
            $externalId,
            $logoUrl
        );

        if (null === $expectedException) {
            $this->assertEquals($title, $command->title);
            $this->assertEquals($bitrix24PartnerNumber, $command->bitrix24PartnerNumber);
            $this->assertEquals($site, $command->site);
            $this->assertEquals($email, $command->email);
            $this->assertEquals($openLineId, $command->openLineId);
            $this->assertEquals($externalId, $command->externalId);
            $this->assertEquals($logoUrl, $command->logoUrl);
        }
    }

    public static function dataForCommand(): \Generator
    {
        yield 'validCommand' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            'https://example.com/logo.png',
            null,
            null,
        ];

        yield 'validCommandWithMinimalFields' => [
            'Test Partner',
            456,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ];

        yield 'emptyTitle' => [
            '',
            123,
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
            'title must be a non-empty string',
        ];

        yield 'emptySite' => [
            'Test Partner',
            123,
            '',
            'test@example.com',
            'line-123',
            'ext-123',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
            'site must be null or non-empty string',
        ];

        yield 'emptyEmail' => [
            'Test Partner',
            123,
            'https://example.com',
            '',
            'line-123',
            'ext-123',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
            'email must be null or non-empty string',
        ];

        yield 'negativeBitrix24PartnerNumber' => [
            'Test Partner',
            -1,
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
            'bitrix24PartnerNumber must be non-negative integer',
        ];

        yield 'emptyOpenLineId' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            '',
            'ext-123',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
            'openLineId must be null or non-empty string',
        ];

        yield 'emptyExternalId' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            'line-123',
            '',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
            'externalId must be null or non-empty string',
        ];

        yield 'emptyLogoUrl' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            '',
            \InvalidArgumentException::class,
            'logoUrl must be null or non-empty string',
        ];
    }
}
