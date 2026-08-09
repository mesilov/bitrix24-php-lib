<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\News\UseCase\Create;

use Bitrix24\Lib\News\UseCase\Create\Command;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Command::class)]
final class CommandTest extends TestCase
{
    #[Test]
    public function commandStoresTitleAndText(): void
    {
        $command = new Command('Hello', 'World');

        self::assertSame('Hello', $command->title);
        self::assertSame('World', $command->text);
    }

    #[Test]
    #[DataProvider('emptyFieldsProvider')]
    public function commandThrowsOnEmptyFields(string $title, string $text): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Command($title, $text);
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
