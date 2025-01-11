<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl\Command;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Generator;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        string $oldDomainUrl,
        string $newDomainUrl,
        ?string $expectedException
    )
    {

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        new Command($oldDomainUrl, $newDomainUrl);

    }

    public static function dataForCommand(): Generator
    {

        $oldDomainUrl = 'https://'.Uuid::v7()->toRfc4122() . '-test.bitrix24.com';
        $newDomainUrl = Uuid::v7()->toRfc4122() . '-test.bitrix24.com';

        yield 'validDomainUrl' => [
            $oldDomainUrl,
            $newDomainUrl,
            InvalidArgumentException::class
        ];
    }
}