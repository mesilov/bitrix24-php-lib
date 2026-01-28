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
    public function testValidCommand(): void
    {
        $id = Uuid::v7();
        $email = 'john.doe@example.com';
        $verifiedAt = new CarbonImmutable();

        $command = new Command(
            $id,
            $email,
            $verifiedAt
        );

        self::assertEquals($id, $command->contactPersonId);
        self::assertSame($email, $command->email);
        self::assertSame($verifiedAt, $command->emailVerifiedAt);
    }

    #[Test]
    public function testValidCommandWithoutDate(): void
    {
        $id = Uuid::v7();
        $email = 'john.doe@example.com';

        $command = new Command(
            $id,
            $email
        );

        self::assertEquals($id, $command->contactPersonId);
        self::assertSame($email, $command->email);
        self::assertNull($command->emailVerifiedAt);
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailThrows(string $email): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format.');

        new Command(
            Uuid::v7(),
            $email
        );
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'invalid format' => ['not-an-email'],
        ];
    }
}
