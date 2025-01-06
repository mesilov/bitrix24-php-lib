<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish\Command;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[TestDox('test finish installation for Command')]
    public function testValidCommand(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $applicationToken = Uuid::v7()->toRfc4122();

        $command = new Command(
            $applicationToken,
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId()
        );
        $this->assertInstanceOf(Command::class, $command);
    }

    public function testEmptyApplicationToken(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application token cannot be empty.');

        new Command(
            '',
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId()
        );
    }


    public function testEmptyMemberId(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $applicationToken = Uuid::v7()->toRfc4122();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member ID cannot be empty.');

        new Command(
            $applicationToken,
            '',
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId()
        );
    }

    public function testValidDomainUrl(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $applicationToken = Uuid::v7()->toRfc4122();


        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain URL is not valid.');

        new Command(
            $applicationToken,
            $bitrix24Account->getMemberId(),
            '',
            $bitrix24Account->getBitrix24UserId()
        );
    }
}