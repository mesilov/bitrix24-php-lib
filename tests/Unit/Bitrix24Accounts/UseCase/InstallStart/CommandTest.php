<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart\Command;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
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
        Uuid $uuid,
        int $bitrix24UserId,
        bool $isBitrix24UserAdmin,
        string $memberId,
        string $domainUrl,
        AuthToken $authToken,
        int $applicationVersion,
        Scope $applicationScope,
        ?string $expectedException,
        ?string $expectedExceptionMessage,
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        if (null !== $expectedExceptionMessage) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        new Command(
            $uuid,
            $bitrix24UserId,
            $isBitrix24UserAdmin,
            $memberId,
            $domainUrl,
            $authToken,
            $applicationVersion,
            $applicationScope
        );
    }

    public static function dataForCommand(): \Generator
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        yield 'emptyMemberId' => [
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            '',
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getAuthToken(),
            $bitrix24Account->getApplicationVersion(),
            $bitrix24Account->getApplicationScope(),
            \InvalidArgumentException::class,
            'Member ID must be a non-empty string.'
        ];

        yield 'validDomainUrl' => [
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            '',
            $bitrix24Account->getAuthToken(),
            $bitrix24Account->getApplicationVersion(),
            $bitrix24Account->getApplicationScope(),
            \InvalidArgumentException::class,
            'Domain URL is not valid.'
        ];

        yield 'validBitrix24UserId' => [
            $bitrix24Account->getId(),
            0,
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getAuthToken(),
            $bitrix24Account->getApplicationVersion(),
            $bitrix24Account->getApplicationScope(),
            \InvalidArgumentException::class,
            'Bitrix24 User ID must be a positive integer.'
        ];

        yield 'validApplicationToken' => [
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getAuthToken(),
            0,
            $bitrix24Account->getApplicationScope(),
            \InvalidArgumentException::class,
            'Application version must be a positive integer.'
        ];
    }
}
