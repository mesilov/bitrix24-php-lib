<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\News\UseCase\Update;

use Bitrix24\Lib\News\UseCase\Update\Command;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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
    public function commandStoresAllFields(): void
    {
        $id = Uuid::v7();

        $command = new Command($id, 'New title', 'New text');

        self::assertTrue($command->id->equals($id));
        self::assertSame('New title', $command->title);
        self::assertSame('New text', $command->text);
    }

    #[Test]
    #[DataProvider('emptyFieldsProvider')]
    public function commandThrowsOnEmptyFields(string $title, string $text): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Command(Uuid::v7(), $title, $text);
    }

    public static function emptyFieldsProvider(): array
    {
        return [
            'empty title' => ['', 'text'],
            'whitespace title' => ['  ', 'text'],
            'empty text' => ['title', ''],
            'whitespace text' => ['title', '  '],
        ];
    }
}
