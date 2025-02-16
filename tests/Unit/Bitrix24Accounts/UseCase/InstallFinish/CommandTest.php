<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish\Command;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;


/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('dataForCommand')]
    public function testValidCommand(
        string  $applicationToken,
        string  $memberId,
        string  $domainUrl,
        int     $bitrix24UserId,
        ?string $expectedException,
        ?string $expectedExceptionMessage,
    ): void
    {

        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $domain = new Domain($domainUrl);
        new Command(
            $applicationToken,
            $memberId,
            $domain,
            $bitrix24UserId
        );
    }

    public static function dataForCommand(): \Generator
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
            \InvalidArgumentException::class,
            'Application token cannot be empty.'
        ];

        yield 'emptyMemberId' => [
            $applicationToken,
            '',
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId(),
            \InvalidArgumentException::class,
            'Member ID cannot be empty.'
        ];

        yield 'invalidDomain' => [
            $applicationToken,
            $bitrix24Account->getMemberId(),
            '',
            $bitrix24Account->getBitrix24UserId(),
            \InvalidArgumentException::class,
            sprintf('Invalid domain: %s', '')
        ];
    }
}
