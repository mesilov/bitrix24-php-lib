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
    #[DataProvider('commandDataProvider')]
    public function testCommand(
        Uuid $applicationInstallationId,
        FullName $fullName,
        int $bitrix24UserId,
        UserAgentInfo $userAgentInfo,
        ?string $email = null,
        ?PhoneNumber $mobilePhoneNumber = null,
        ?string $comment = null,
        ?string $externalId = null,
        ?Uuid $bitrix24PartnerId = null,
        ?string $expectedException = null,
    ): void {
        if (null !== $expectedException) {
            $this->expectException($expectedException);
        }

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

    public static function commandDataProvider(): array
    {
        $fullName = new FullName('John Doe');
        $userAgentInfo = new UserAgentInfo(null);

        return [
            'valid data' => [
                Uuid::v7(),
                $fullName,
                123,
                $userAgentInfo,
                'john.doe@example.com',
                new PhoneNumber(),
                'Test comment',
                'ext-123',
                Uuid::v7(),
            ],
            'invalid email: empty' => [
                Uuid::v7(),
                $fullName,
                123,
                $userAgentInfo,
                '',
                null,
                null,
                null,
                null,
                \InvalidArgumentException::class,
            ],
            'invalid email: spaces' => [
                Uuid::v7(),
                $fullName,
                123,
                $userAgentInfo,
                '   ',
                null,
                null,
                null,
                null,
                \InvalidArgumentException::class,
            ],
            'invalid email: format' => [
                Uuid::v7(),
                $fullName,
                123,
                $userAgentInfo,
                'not-an-email',
                null,
                null,
                null,
                null,
                \InvalidArgumentException::class,
            ],
            'invalid external id: empty string' => [
                Uuid::v7(),
                $fullName,
                123,
                $userAgentInfo,
                null,
                null,
                null,
                ' ',
                null,
                \InvalidArgumentException::class,
            ],
            'invalid user id: zero' => [
                Uuid::v7(),
                $fullName,
                0,
                $userAgentInfo,
                null,
                null,
                null,
                null,
                null,
                \InvalidArgumentException::class,
            ],
            'invalid user id: negative' => [
                Uuid::v7(),
                $fullName,
                -1,
                $userAgentInfo,
                null,
                null,
                null,
                null,
                null,
                \InvalidArgumentException::class,
            ],
        ];
    }
}
