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
        int $bitrix24PartnerNumber,
        ?string $site,
        ?string $email,
        ?string $openLineId,
        ?string $externalId,
        ?string $expectedException
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        $command = new Command(
            $title,
            $bitrix24PartnerNumber,
            $site,
            null,
            $email,
            $openLineId,
            $externalId
        );

        if (null === $expectedException) {
            $this->assertEquals($title, $command->title);
            $this->assertEquals($bitrix24PartnerNumber, $command->bitrix24PartnerNumber);
            $this->assertEquals($site, $command->site);
            $this->assertEquals($email, $command->email);
            $this->assertEquals($openLineId, $command->openLineId);
            $this->assertEquals($externalId, $command->externalId);
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
            null,
        ];

        yield 'emptyTitle' => [
            '',
            123,
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
        ];

        yield 'emptySite' => [
            'Test Partner',
            123,
            '',
            'test@example.com',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
        ];

        yield 'emptyEmail' => [
            'Test Partner',
            123,
            'https://example.com',
            '',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
        ];

        yield 'negativeBitrix24PartnerNumber' => [
            'Test Partner',
            -1,
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
        ];

        yield 'emptyOpenLineId' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            '',
            'ext-123',
            \InvalidArgumentException::class,
        ];

        yield 'emptyExternalId' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            'line-123',
            '',
            \InvalidArgumentException::class,
        ];
    }
}
