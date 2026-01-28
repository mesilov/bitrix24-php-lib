<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\InstallContactPerson;

use Bitrix24\Lib\ApplicationInstallations\UseCase\InstallContactPerson\Command;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Darsyn\IP\Version\Multi as IP;
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
        $applicationInstallationId = Uuid::v7();
        $fullName = new FullName('John Doe');
        $bitrix24UserId = 123;
        $userAgentInfo = new UserAgentInfo(IP::factory('127.0.0.1'));
        $email = 'john.doe@example.com';
        $mobilePhoneNumber = new PhoneNumber();
        $comment = 'Test comment';
        $externalId = 'ext-123';
        $bitrix24PartnerId = Uuid::v7();

        $command = new Command(
            $applicationInstallationId,
            $fullName,
            $bitrix24UserId,
            $userAgentInfo,
            $email,
            $mobilePhoneNumber,
            $comment,
            $externalId,
            $bitrix24PartnerId
        );

        self::assertSame($applicationInstallationId, $command->applicationInstallationId);
        self::assertSame($fullName, $command->fullName);
        self::assertSame($bitrix24UserId, $command->bitrix24UserId);
        self::assertSame($userAgentInfo, $command->userAgentInfo);
        self::assertSame($email, $command->email);
        self::assertSame($mobilePhoneNumber, $command->mobilePhoneNumber);
        self::assertSame($comment, $command->comment);
        self::assertSame($externalId, $command->externalId);
        self::assertSame($bitrix24PartnerId, $command->bitrix24PartnerId);
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailThrows(string $email): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format.');

        new Command(
            Uuid::v7(),
            new FullName('John Doe'),
            123,
            new UserAgentInfo(null),
            $email,
            null,
            null,
            null,
            null
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

    #[Test]
    public function testEmptyExternalIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('External ID cannot be empty if provided.');

        new Command(
            Uuid::v7(),
            new FullName('John Doe'),
            123,
            new UserAgentInfo(null),
            null,
            null,
            null,
            ' ',
            null
        );
    }

    #[Test]
    #[DataProvider('invalidUserIdProvider')]
    public function testInvalidBitrix24UserIdThrows(int $userId): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 User ID must be a positive integer.');

        new Command(
            Uuid::v7(),
            new FullName('John Doe'),
            $userId,
            new UserAgentInfo(null),
            null,
            null,
            null,
            null,
            null
        );
    }

    public static function invalidUserIdProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }
}
