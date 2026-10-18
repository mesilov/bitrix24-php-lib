<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Command;
use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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
            $applicationToken,
            false
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

        // Valid case
        yield 'validCommand' => [
            $bitrix24AccountBuilder->getMemberId(),
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            $applicationToken,
            null,
        ];

        // Empty memberId
        yield 'emptyMemberId' => [
            '',
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            $applicationToken,
            InvalidArgumentException::class,
        ];

        // Empty applicationToken
        yield 'emptyApplicationToken' => [
            $bitrix24AccountBuilder->getMemberId(),
            new Domain($bitrix24AccountBuilder->getDomainUrl()),
            '',
            InvalidArgumentException::class,
        ];
    }

    #[DataProvider('validCleanFlags')]
    public function testEventPreservesExplicitCleanChoice(string|int $flag, bool $expected): void
    {
        $command = Command::fromEvent([
            'event' => 'ONAPPUNINSTALL',
            'data' => ['CLEAN' => $flag],
            'auth' => ['domain' => 'example.bitrix24.ru', 'member_id' => 'member', 'application_token' => 'secret'],
        ]);
        self::assertSame($expected, $command->clean);
    }

    public static function validCleanFlags(): iterable
    {
        yield ['0', false];
        yield [0, false];
        yield ['1', true];
        yield [1, true];
    }

    #[DataProvider('invalidCleanFlags')]
    public function testEventRejectsMissingOrAmbiguousCleanChoice(mixed $flag): void
    {
        $event = [
            'event' => 'ONAPPUNINSTALL', 'data' => [],
            'auth' => ['domain' => 'example.bitrix24.ru', 'member_id' => 'member', 'application_token' => 'secret'],
        ];
        if ($flag !== null) {
            $event['data']['CLEAN'] = $flag;
        }

        $this->expectException(InvalidArgumentException::class);
        Command::fromEvent($event);
    }

    public static function invalidCleanFlags(): iterable
    {
        foreach ([null, '', 'false', 'true', true, false, 2, -1, [], '01', ' 1'] as $flag) {
            yield [$flag];
        }
    }
}