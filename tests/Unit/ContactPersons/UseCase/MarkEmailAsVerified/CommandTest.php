<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ContactPersons\UseCase\MarkEmailAsVerified;

use Bitrix24\Lib\ContactPersons\UseCase\MarkEmailAsVerified\Command;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Command::class)]
final class CommandTest extends TestCase
{
    #[Test]
    #[DataProvider('commandDataProvider')]
    public function testCommand(
        Uuid $uuid,
        string $email,
        ?CarbonImmutable $emailVerifiedAt = null,
        ?string $expectedException = null,
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        $command = new Command(
            $uuid,
            $email,
            $emailVerifiedAt
        );

        self::assertEquals($uuid, $command->contactPersonId);
        self::assertSame($email, $command->email);
        self::assertEquals($emailVerifiedAt, $command->emailVerifiedAt);
    }

    public static function commandDataProvider(): array
    {
        return [
            'valid data' => [
                Uuid::v7(),
                'john.doe@example.com',
                new CarbonImmutable(),
            ],
            'valid data without date' => [
                Uuid::v7(),
                'john.doe@example.com',
                null,
            ],
            'invalid email: empty' => [
                Uuid::v7(),
                '',
                null,
                \InvalidArgumentException::class,
            ],
            'invalid email: spaces' => [
                Uuid::v7(),
                '   ',
                null,
                \InvalidArgumentException::class,
            ],
            'invalid email: format' => [
                Uuid::v7(),
                'not-an-email',
                null,
                \InvalidArgumentException::class,
            ],
        ];
    }
}
