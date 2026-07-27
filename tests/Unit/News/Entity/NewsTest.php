<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\News\Entity;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Entity\NewsStatus;
use Bitrix24\Lib\News\Events\NewsCreatedEvent;
use Bitrix24\Lib\News\Events\NewsDeletedEvent;
use Bitrix24\Lib\News\Events\NewsPublishedEvent;
use Bitrix24\Lib\News\Events\NewsRevertedToDraftEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(News::class)]
final class NewsTest extends TestCase
{
    #[Test]
    public function constructorCreatesDraftNewsWithProvidedFields(): void
    {
        $news = new News(Uuid::v7(), 'Test title', 'Test text');

        self::assertSame(NewsStatus::draft, $news->getStatus());
        self::assertSame('Test title', $news->getTitle());
        self::assertSame('Test text', $news->getText());
    }

    #[Test]
    public function constructorWithoutEventFlagDoesNotEmitCreatedEvent(): void
    {
        $news = new News(Uuid::v7(), 'Test title', 'Test text');

        self::assertSame([], $news->emitEvents());
    }

    #[Test]
    public function constructorWithEventFlagEmitsCreatedEvent(): void
    {
        $news = new News(Uuid::v7(), 'Test title', 'Test text', isEmitNewsCreatedEvent: true);

        $events = $news->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsCreatedEvent::class, $events[0]);
    }

    #[Test]
    public function changeTitleUpdatesTitle(): void
    {
        $news = new News(Uuid::v7(), 'Old title', 'Test text');

        $news->changeTitle('New title');

        self::assertSame('New title', $news->getTitle());
    }

    #[Test]
    public function changeTitleThrowsOnEmptyTitle(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(InvalidArgumentException::class);

        $news->changeTitle('  ');
    }

    #[Test]
    public function changeTextUpdatesText(): void
    {
        $news = new News(Uuid::v7(), 'Test title', 'Old text');

        $news->changeText('New text');

        self::assertSame('New text', $news->getText());
    }

    #[Test]
    public function changeTextThrowsOnEmptyText(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(InvalidArgumentException::class);

        $news->changeText('');
    }

    #[Test]
    public function publishChangesStatusToPublished(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');

        $news->publish();

        self::assertSame(NewsStatus::published, $news->getStatus());
        $events = $news->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsPublishedEvent::class, $events[0]);
    }

    #[Test]
    public function publishThrowsWhenNotInDraftStatus(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');
        $news->publish();

        $this->expectException(LogicException::class);

        $news->publish();
    }

    #[Test]
    public function revertToDraftChangesStatusToDraft(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');
        $news->publish();
        $news->emitEvents();

        $news->revertToDraft();

        self::assertSame(NewsStatus::draft, $news->getStatus());
        $events = $news->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsRevertedToDraftEvent::class, $events[0]);
    }

    #[Test]
    public function revertToDraftThrowsWhenNotPublished(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(LogicException::class);

        $news->revertToDraft();
    }

    #[Test]
    public function markAsDeletedChangesStatusToDeleted(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');

        $news->markAsDeleted();

        self::assertSame(NewsStatus::deleted, $news->getStatus());
        $events = $news->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsDeletedEvent::class, $events[0]);
    }

    #[Test]
    public function markAsDeletedThrowsWhenAlreadyDeleted(): void
    {
        $news = new News(Uuid::v7(), 'Title', 'Text');
        $news->markAsDeleted();

        $this->expectException(LogicException::class);

        $news->markAsDeleted();
    }

    #[Test]
    #[DataProvider('emptyFieldsProvider')]
    public function constructorThrowsOnEmptyFields(string $title, string $text): void
    {
        $this->expectException(InvalidArgumentException::class);

        new News(Uuid::v7(), $title, $text);
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
