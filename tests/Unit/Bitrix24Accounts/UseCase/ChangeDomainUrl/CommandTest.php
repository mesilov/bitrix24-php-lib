<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl\Command;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use Symfony\Component\Uid\Uuid;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{

    public function testValidDomainUrl(): void
    {
        $oldDomainUrl = 'https://'.Uuid::v7()->toRfc4122() . '-test.bitrix24.com';
        $newDomainUrl = Uuid::v7()->toRfc4122() . '-test.bitrix24.com';

        $this->expectException(InvalidArgumentException::class);
        new Command($oldDomainUrl, $newDomainUrl);
    }

    protected function setUp(): void
    {

    }
}