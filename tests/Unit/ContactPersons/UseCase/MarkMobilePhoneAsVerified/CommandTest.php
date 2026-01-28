<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ContactPersons\UseCase\MarkMobilePhoneAsVerified;

use Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified\Command;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
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
        $contactPersonId = Uuid::v7();
        $phone = new PhoneNumber();
        $phoneVerifiedAt = CarbonImmutable::now();

        $command = new Command(
            $contactPersonId,
            $phone,
            $phoneVerifiedAt
        );

        self::assertSame($contactPersonId, $command->contactPersonId);
        self::assertSame($phone, $command->phone);
        self::assertSame($phoneVerifiedAt, $command->phoneVerifiedAt);
    }

    #[Test]
    public function testValidCommandWithoutDate(): void
    {
        $contactPersonId = Uuid::v7();
        $phone = new PhoneNumber();

        $command = new Command(
            $contactPersonId,
            $phone
        );

        self::assertSame($contactPersonId, $command->contactPersonId);
        self::assertSame($phone, $command->phone);
        self::assertNull($command->phoneVerifiedAt);
    }
}
