<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl\Command;
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
        string $oldDomain,
        string $newDomain,
        ?string $expectedException
    )
    {

        if ($expectedException !== null) {
            $this->expectException(\InvalidArgumentException::class);
        }

        $command = new Command($oldDomain, $newDomain);

        if ($expectedException == null) {
            $this->assertInstanceOf(Command::class, $command);
        }

    }

    public static function dataForCommand(): \Generator
    {
        $invalidOldDomain = 'invalid_domain.com';
        $invalidNewDomain = 'valid.com';

        $validOldDomain = 'example.com';
        $validNewDomain = 'example.org';

        yield 'invalidDomain' => [
            $invalidOldDomain,
            $invalidNewDomain,
            \InvalidArgumentException::class
        ];

        yield 'validDomain' => [
            $validOldDomain,
            $validNewDomain,
            null // Здесь исключение не ожидается
        ];
    }
}
