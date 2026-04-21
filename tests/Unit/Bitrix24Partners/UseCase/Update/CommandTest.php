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
            $externalId
        );

        if (null === $expectedException) {
            $this->assertEquals($id, $command->id);
            $this->assertEquals($title, $command->title);
            $this->assertEquals($site, $command->site);
            $this->assertEquals($email, $command->email);
            $this->assertEquals($openLineId, $command->openLineId);
            $this->assertEquals($externalId, $command->externalId);
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
            null,
            null,
        ];

        yield 'nullValues' => [
            $id,
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
            \InvalidArgumentException::class,
            'title must be null or non-empty string',
        ];

        yield 'emptySite' => [
            $id,
            'Updated Partner',
            '',
            'test@example.com',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'site must be null or non-empty string',
        ];

        yield 'emptyEmail' => [
            $id,
            'Updated Partner',
            'https://example.com',
            '',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'email must be null or non-empty string',
        ];

        yield 'invalidEmail' => [
            $id,
            'Updated Partner',
            'https://example.com',
            'invalid-email',
            'line-123',
            'ext-123',
            \InvalidArgumentException::class,
            'email invalid-email is invalid',
        ];

        yield 'emptyOpenLineId' => [
            $id,
            'Updated Partner',
            'https://example.com',
            'test@example.com',
            '',
            'ext-123',
            \InvalidArgumentException::class,
            'openLineId must be null or non-empty string',
        ];

        yield 'emptyExternalId' => [
            $id,
            'Updated Partner',
            'https://example.com',
            'test@example.com',
            'line-123',
            '',
            \InvalidArgumentException::class,
            'externalId must be null or non-empty string',
        ];
    }
}
