<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\UnlinkContactPerson;

use Bitrix24\Lib\ApplicationInstallations\UseCase\UnlinkContactPerson\Command;
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
        $applicationInstallationId = Uuid::v7();
        $comment = 'Test unlink comment';

        $command = new Command(
            $contactPersonId,
            $applicationInstallationId,
            $comment
        );

        self::assertSame($contactPersonId, $command->contactPersonId);
        self::assertSame($applicationInstallationId, $command->applicationInstallationId);
        self::assertSame($comment, $command->comment);
    }

    #[Test]
    public function testValidCommandWithoutComment(): void
    {
        $contactPersonId = Uuid::v7();
        $applicationInstallationId = Uuid::v7();

        $command = new Command(
            $contactPersonId,
            $applicationInstallationId
        );

        self::assertSame($contactPersonId, $command->contactPersonId);
        self::assertSame($applicationInstallationId, $command->applicationInstallationId);
        self::assertNull($command->comment);
    }
}
