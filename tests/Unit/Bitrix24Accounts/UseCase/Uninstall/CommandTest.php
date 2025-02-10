<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall\Command;
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
    #[DataProvider('dataForCommandValidToken')]
    public function testValidTokenForCommand(
        string $applicationToken,
    ): void {
        $command = new Command($applicationToken);
        $this->assertEquals($applicationToken,$command->applicationToken);
    }

    #[Test]
    #[DataProvider('dataForCommandEmptyToken')]
    public function testEmptyTokenForCommand(
        string $applicationToken,
        ?string $expectedException,
        ?string $expectedExceptionMessage,
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        new Command($applicationToken);
    }

    public static function dataForCommandValidToken(): \Generator
    {
        yield 'validApplicationToken' => [
            Uuid::v7()->toRfc4122()
        ];
    }

    public static function dataForCommandEmptyToken(): \Generator
    {
        yield 'emptyApplicationToken' => [
            '',
            \InvalidArgumentException::class,
            'Application token must be a non-empty string.',
        ];
    }
}
