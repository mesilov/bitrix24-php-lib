<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall\Command;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Generator;
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        string $applicationToken,
        ?string $expectedException,
        ?string $expectedExceptionMessage,
    )
    {

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        if ($expectedExceptionMessage !== null) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

       new Command($applicationToken);

    }

    public static function dataForCommand(): Generator
    {
        yield 'validApplicationToken' => [
            '',
            InvalidArgumentException::class,
            'Empty application token application token.'
        ];
    }
}