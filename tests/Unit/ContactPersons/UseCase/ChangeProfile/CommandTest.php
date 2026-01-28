<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ContactPersons\UseCase\ChangeProfile;

use Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile\Command;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
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
        $command = new Command(
            Uuid::v7(),
            new FullName('John Doe'),
            'john.doe@example.com',
            $this->createDummyPhone()
        );

        self::assertSame('john.doe@example.com', $command->email);
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailThrows(string $email): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format.');

        new Command(
            Uuid::v7(),
            new FullName('John Doe'),
            $email,
            $this->createDummyPhone()
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

    private function createDummyPhone(): PhoneNumber
    {
        // Нам не важно содержимое, т.к. Command телефон не валидирует.
        return new PhoneNumber();
    }
}
