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
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
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

        $command = new Command($applicationToken);

        if ($expectedException == null) {
            $this->assertInstanceOf(Command::class, $command);
        }
    }

    public static function dataForCommand(): \Generator
    {

        $applicationToken = Uuid::v7()->toRfc4122();

        yield 'validApplicationToken' => [
            $applicationToken,
            null,
            null,
        ];

        yield 'emptyApplicationToken' => [
            '',
            \InvalidArgumentException::class,
            'Empty application token application token.',
        ];
    }
}
