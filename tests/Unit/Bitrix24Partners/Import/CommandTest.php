<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\Import;

use Bitrix24\Lib\Bitrix24Partners\Import\Command;
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
        ?string $logoUrl,
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
            $logoUrl
        );

        if (null === $expectedException) {
            $this->assertEquals($title, $command->title);
            $this->assertEquals($bitrix24PartnerNumber, $command->bitrix24PartnerNumber);
            $this->assertEquals($site, $command->site);
            $this->assertEquals($email, $command->email);
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
            'https://example.com/logo.png',
            null,
        ];

        yield 'validCommandWithMinimalFields' => [
            'Test Partner',
            456,
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
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
        ];

        yield 'emptySite' => [
            'Test Partner',
            123,
            '',
            'test@example.com',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
        ];

        yield 'emptyEmail' => [
            'Test Partner',
            123,
            'https://example.com',
            '',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
        ];

        yield 'negativeBitrix24PartnerNumber' => [
            'Test Partner',
            -1,
            'https://example.com',
            'test@example.com',
            'https://example.com/logo.png',
            \InvalidArgumentException::class,
        ];

        yield 'emptyLogoUrl' => [
            'Test Partner',
            123,
            'https://example.com',
            'test@example.com',
            '',
            \InvalidArgumentException::class,
        ];
    }
}
