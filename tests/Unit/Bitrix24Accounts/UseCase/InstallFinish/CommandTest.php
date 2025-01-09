<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish\Command;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Generator;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{

    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        string $applicationToken,
        string $memberId,
        string $domainUrl,
        int $bitrix24UserId,
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

        new Command(
            $applicationToken,
            $memberId,
            $domainUrl,
            $bitrix24UserId
        );

    }

    public static function dataForCommand(): Generator
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        yield 'emptyApplicationToken' => [
            '',
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId(),
            InvalidArgumentException::class,
            'Application token cannot be empty.'
        ];

        yield 'emptyMemberId' => [
            $applicationToken,
            '',
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId(),
            InvalidArgumentException::class,
            'Member ID cannot be empty.'
        ];

        yield 'validDomainUrl' => [
            $applicationToken,
            $bitrix24Account->getMemberId(),
            '',
            $bitrix24Account->getBitrix24UserId(),
            InvalidArgumentException::class,
            'Domain URL is not valid.'
        ];

    }
}