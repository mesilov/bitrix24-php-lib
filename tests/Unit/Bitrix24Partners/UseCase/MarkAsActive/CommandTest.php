<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\MarkAsActive;

use Bitrix24\Lib\Bitrix24Partners\UseCase\MarkAsActive\Command;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    #[Test]
    public function testValidCommand(): void
    {
        $id = Uuid::v7();
        $comment = 'Activation comment';
        $command = new Command($id, $comment);

        $this->assertEquals($id, $command->id);
        $this->assertEquals($comment, $command->comment);
    }

    #[Test]
    public function testMinimalCommand(): void
    {
        $id = Uuid::v7();
        $command = new Command($id);

        $this->assertEquals($id, $command->id);
        $this->assertNull($command->comment);
    }
}
