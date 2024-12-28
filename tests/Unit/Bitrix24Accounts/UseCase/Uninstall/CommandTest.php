<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall\Command;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    public function testValidApplicationToken(): void
    {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty application token or invalid application token.');

        new Command('123_test_string');
    }
}