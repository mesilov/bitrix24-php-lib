<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Command;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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
        string  $memberId,
        Domain  $domain,
        string  $applicationToken,
        ?string $expectedException,
    ): void
    {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        $command = new Command(
            $domain,
            $memberId,
            $applicationToken
        );

        if (null === $expectedException) {
            $this->assertInstanceOf(Command::class, $command);
        }
    }

    public static function dataForCommand(): \Generator
    {
        $applicationToken = Uuid::v7()->toRfc4122();

        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withInstalled()
            ->withApplicationToken($applicationToken)
            ->withStatus(Bitrix24AccountStatus::active)
            ->build();

        // Валидный кейс
        yield 'validCommand' => [
            $bitrix24AccountBuilder->getMemberId(),
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            $applicationToken,
            null,
        ];

        // Пустой memberId
        yield 'emptyMemberId' => [
            '',
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            $applicationToken,
            \InvalidArgumentException::class,
        ];

        // Пустой applicationToken
        yield 'emptyApplicationToken' => [
            $bitrix24AccountBuilder->getMemberId(),
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            '',
            \InvalidArgumentException::class,
        ];
    }
}