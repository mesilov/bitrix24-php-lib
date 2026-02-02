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
    #[DataProvider('commandDataProvider')]
    public function testCommand(
        Uuid $contactPersonId,
        FullName $fullName,
        string $email,
        PhoneNumber $mobilePhoneNumber,
        ?string $expectedException = null,
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

        $command = new Command(
            $contactPersonId,
            $fullName,
            $email,
            $mobilePhoneNumber
        );

        self::assertEquals($contactPersonId, $command->contactPersonId);
        self::assertEquals($fullName, $command->fullName);
        self::assertSame($email, $command->email);
        self::assertEquals($mobilePhoneNumber, $command->mobilePhoneNumber);
    }

    public static function commandDataProvider(): array
    {
        $fullName = new FullName('John Doe');

        return [
            'valid data' => [
                Uuid::v7(),
                $fullName,
                'john.doe@example.com',
                new PhoneNumber(),
            ],
            'empty email is valid' => [
                Uuid::v7(),
                $fullName,
                '',
                new PhoneNumber(),
            ],
            'invalid email format' => [
                Uuid::v7(),
                $fullName,
                'not-an-email',
                new PhoneNumber(),
                InvalidArgumentException::class,
            ],
        ];
    }
}
