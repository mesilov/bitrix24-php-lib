<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\News\Entity;

use Bitrix24\Lib\News\Entity\News;
use Bitrix24\Lib\News\Entity\NewsStatus;
use Bitrix24\Lib\News\Events\NewsCreatedEvent;
use Bitrix24\Lib\News\Events\NewsDeletedEvent;
use Bitrix24\Lib\News\Events\NewsImageChangedEvent;
use Bitrix24\Lib\News\Events\NewsPublishedEvent;
use Bitrix24\Lib\News\Events\NewsRevertedToDraftEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
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
        $newsItem = new News(Uuid::v7(), 'Test title', 'Test text');

        self::assertSame(NewsStatus::draft, $newsItem->getStatus());
        self::assertSame('Test title', $newsItem->getTitle());
        self::assertSame('Test text', $newsItem->getText());
        self::assertNull($newsItem->getImageUrl());
    }

    #[Test]
    public function constructorAcceptsImageUrl(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text', 'https://example.com/image.png');

        self::assertSame('https://example.com/image.png', $newsItem->getImageUrl());
    }

    #[Test]
    public function constructorWithoutEventFlagDoesNotEmitCreatedEvent(): void
    {
        $newsItem = new News(Uuid::v7(), 'Test title', 'Test text');

        self::assertSame([], $newsItem->emitEvents());
    }

    #[Test]
    public function constructorWithEventFlagEmitsCreatedEvent(): void
    {
        $newsItem = new News(Uuid::v7(), 'Test title', 'Test text', isEmitNewsCreatedEvent: true);

        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsCreatedEvent::class, $events[0]);
    }

    #[Test]
    public function changeTitleUpdatesTitle(): void
    {
        $newsItem = new News(Uuid::v7(), 'Old title', 'Test text');

        $newsItem->changeTitle('New title');

        self::assertSame('New title', $newsItem->getTitle());
    }

    #[Test]
    public function changeTitleThrowsOnEmptyTitle(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(InvalidArgumentException::class);

        $newsItem->changeTitle('  ');
    }

    #[Test]
    public function changeTextUpdatesText(): void
    {
        $newsItem = new News(Uuid::v7(), 'Test title', 'Old text');

        $newsItem->changeText('New text');

        self::assertSame('New text', $newsItem->getText());
    }

    #[Test]
    public function changeTextThrowsOnEmptyText(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(InvalidArgumentException::class);

        $newsItem->changeText('');
    }

    #[Test]
    public function publishChangesStatusToPublished(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $newsItem->publish();

        self::assertSame(NewsStatus::published, $newsItem->getStatus());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsPublishedEvent::class, $events[0]);
    }

    #[Test]
    public function publishThrowsWhenNotInDraftStatus(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->publish();

        $this->expectException(LogicException::class);

        $newsItem->publish();
    }

    #[Test]
    public function publishUsesProvidedTimestamp(): void
    {
        $frozenTime = CarbonImmutable::parse('2025-01-15 10:30:00');
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $newsItem->publish($frozenTime);

        self::assertSame($frozenTime, $newsItem->getUpdatedAt());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsPublishedEvent::class, $events[0]);
        self::assertSame($frozenTime, $events[0]->timestamp);
    }

    #[Test]
    public function revertToDraftChangesStatusToDraft(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->publish();
        $newsItem->emitEvents();

        $newsItem->revertToDraft();

        self::assertSame(NewsStatus::draft, $newsItem->getStatus());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsRevertedToDraftEvent::class, $events[0]);
    }

    #[Test]
    public function revertToDraftThrowsWhenNotPublished(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(LogicException::class);

        $newsItem->revertToDraft();
    }

    #[Test]
    public function revertToDraftUsesProvidedTimestamp(): void
    {
        $frozenTime = CarbonImmutable::parse('2025-01-15 10:30:00');
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->publish();
        $newsItem->emitEvents();

        $newsItem->revertToDraft($frozenTime);

        self::assertSame($frozenTime, $newsItem->getUpdatedAt());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsRevertedToDraftEvent::class, $events[0]);
        self::assertSame($frozenTime, $events[0]->timestamp);
    }

    #[Test]
    public function markAsDeletedChangesStatusToDeleted(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $newsItem->markAsDeleted();

        self::assertSame(NewsStatus::deleted, $newsItem->getStatus());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsDeletedEvent::class, $events[0]);
    }

    #[Test]
    public function markAsDeletedThrowsWhenAlreadyDeleted(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->markAsDeleted();

        $this->expectException(LogicException::class);

        $newsItem->markAsDeleted();
    }

    #[Test]
    public function markAsDeletedUsesProvidedTimestamp(): void
    {
        $frozenTime = CarbonImmutable::parse('2025-01-15 10:30:00');
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $newsItem->markAsDeleted($frozenTime);

        self::assertSame($frozenTime, $newsItem->getUpdatedAt());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsDeletedEvent::class, $events[0]);
        self::assertSame($frozenTime, $events[0]->timestamp);
    }

    #[Test]
    public function attachImageSetsImageUrl(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $newsItem->attachImage('https://example.com/image.png');

        self::assertSame('https://example.com/image.png', $newsItem->getImageUrl());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsImageChangedEvent::class, $events[0]);
        self::assertSame('https://example.com/image.png', $events[0]->imageUrl);
        self::assertNull($events[0]->oldImageUrl);
    }

    #[Test]
    public function attachImageIsNoOpWhenSameUrl(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->attachImage('https://example.com/image.png');
        $newsItem->emitEvents();

        $newsItem->attachImage('https://example.com/image.png');

        self::assertSame([], $newsItem->emitEvents());
    }

    #[Test]
    public function attachImageEmitsOldAndNewUrlOnReplace(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->attachImage('https://example.com/old-image.png');
        $newsItem->emitEvents();

        $newsItem->attachImage('https://example.com/new-image.png');

        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsImageChangedEvent::class, $events[0]);
        self::assertSame('https://example.com/new-image.png', $events[0]->imageUrl);
        self::assertSame('https://example.com/old-image.png', $events[0]->oldImageUrl);
    }

    #[Test]
    public function attachImageThrowsOnEmptyUrl(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $this->expectException(InvalidArgumentException::class);

        $newsItem->attachImage('  ');
    }

    #[Test]
    public function detachImageSetsNull(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');
        $newsItem->attachImage('https://example.com/image.png');
        $newsItem->emitEvents();

        $newsItem->detachImage();

        self::assertNull($newsItem->getImageUrl());
        $events = $newsItem->emitEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NewsImageChangedEvent::class, $events[0]);
        self::assertNull($events[0]->imageUrl);
        self::assertSame('https://example.com/image.png', $events[0]->oldImageUrl);
    }

    #[Test]
    public function detachImageIsNoOpWhenAlreadyNull(): void
    {
        $newsItem = new News(Uuid::v7(), 'Title', 'Text');

        $newsItem->detachImage();

        self::assertSame([], $newsItem->emitEvents());
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
