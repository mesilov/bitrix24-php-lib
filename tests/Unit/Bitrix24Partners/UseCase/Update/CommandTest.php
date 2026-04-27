<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Update;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Update\Command;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        Uuid $id,
        ?string $title,
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
            $id,
            $title,
            $site,
            null, // phone
            $email,
            $openLineId,
            $externalId,
            $logoUrl
        );

        if (null === $expectedException) {
            $this->assertEquals($id, $command->id);
            $this->assertEquals($title, $command->title);
            $this->assertEquals($site, $command->site);
            $this->assertEquals($email, $command->email);
            $this->assertEquals($openLineId, $command->openLineId);
            $this->assertEquals($externalId, $command->externalId);
            $this->assertEquals($logoUrl, $command->logoUrl);
        }
    }

    public static function dataForCommand(): \Generator
    {
        $id = Uuid::v7();

        yield 'validCommand' => [
            $id,
            'Updated Partner',
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            'https://example.com/logo.png',
            null,
            null,
        ];

        yield 'nullValuesExceptTitle' => [
            $id,
            'Updated Partner',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ];

        yield 'emptyTitle' => [
            $id,
            '',
            'https://example.com',
            'test@example.com',
            'line-123',
            'ext-123',
            null,
            \InvalidArgumentException::class,
            'title must be non-empty string',
        ];

        yield 'invalidEmail' => [
            $id,
            'Updated Partner',
            'https://example.com',
            'invalid-email',
            'line-123',
            'ext-123',
            null,
            \InvalidArgumentException::class,
            'email invalid-email is invalid',
        ];
    }
}
