<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\Lib\News\Events\NewsCreatedEvent;
use Bitrix24\Lib\News\Events\NewsDeletedEvent;
use Bitrix24\Lib\News\Events\NewsImageChangedEvent;
use Bitrix24\Lib\News\Events\NewsPublishedEvent;
use Bitrix24\Lib\News\Events\NewsRevertedToDraftEvent;
use Bitrix24\Lib\News\Events\NewsTextChangedEvent;
use Bitrix24\Lib\News\Events\NewsTitleChangedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

class News extends AggregateRoot
{
    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    private NewsStatus $status = NewsStatus::draft;

    public function __construct(
        private readonly Uuid $id,
        private string $title,
        private string $text,
        private ?string $imageUrl = null,
        private readonly bool $isEmitNewsCreatedEvent = false,
    ) {
        $this->validate();

        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();

        $this->addNewsCreatedEventIfNeeded($this->isEmitNewsCreatedEvent);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getStatus(): NewsStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /**
     * Attach or replace the news image.
     * Idempotent: no-op if the same URL is already set.
     * Emits NewsImageChangedEvent with old and new URL on actual change.
     */
    public function attachImage(string $url): void
    {
        $this->guardImageUrl($url);

        if ($this->imageUrl === $url) {
            return;
        }

        $oldImageUrl = $this->imageUrl;
        $this->imageUrl = $url;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsImageChangedEvent(
            $this->id,
            $this->updatedAt,
            $this->imageUrl,
            $oldImageUrl
        );
    }

    /**
     * Detach the news image.
     * Idempotent: no-op if there is no image.
     * Emits NewsImageChangedEvent with old URL and null new URL.
     */
    public function detachImage(): void
    {
        if (null === $this->imageUrl) {
            return;
        }

        $oldImageUrl = $this->imageUrl;
        $this->imageUrl = null;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsImageChangedEvent(
            $this->id,
            $this->updatedAt,
            null,
            $oldImageUrl
        );
    }

    public function changeTitle(string $title): void
    {
        $this->guardTitle($title);

        $oldTitle = $this->title;
        if ($oldTitle === $title) {
            return;
        }

        $this->title = $title;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsTitleChangedEvent(
            $this->id,
            $this->updatedAt,
            $oldTitle,
            $title
        );
    }

    public function changeText(string $text): void
    {
        $this->guardText($text);

        $oldText = $this->text;
        if ($oldText === $text) {
            return;
        }

        $this->text = $text;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsTextChangedEvent(
            $this->id,
            $this->updatedAt,
            $oldText,
            $text
        );
    }

    public function publish(?CarbonImmutable $publishedAt = null): void
    {
        if (NewsStatus::draft !== $this->status) {
            throw new LogicException(
                sprintf(
                    'you can publish news only from status «draft», now status is «%s»',
                    $this->status->value
                )
            );
        }

        $this->status = NewsStatus::published;
        $this->updatedAt = $publishedAt ?? new CarbonImmutable();

        $this->events[] = new NewsPublishedEvent($this->id, $this->updatedAt);
    }

    public function revertToDraft(?CarbonImmutable $revertedAt = null): void
    {
        if (NewsStatus::published !== $this->status) {
            throw new LogicException(
                sprintf(
                    'you can revert to draft news only from status «published», now status is «%s»',
                    $this->status->value
                )
            );
        }

        $this->status = NewsStatus::draft;
        $this->updatedAt = $revertedAt ?? new CarbonImmutable();

        $this->events[] = new NewsRevertedToDraftEvent($this->id, $this->updatedAt);
    }

    public function markAsDeleted(?CarbonImmutable $deletedAt = null): void
    {
        if (NewsStatus::deleted === $this->status) {
            throw new LogicException('news already in status «deleted»');
        }

        $this->status = NewsStatus::deleted;
        $this->updatedAt = $deletedAt ?? new CarbonImmutable();

        $this->events[] = new NewsDeletedEvent($this->id, $this->updatedAt);
    }

    private function guardTitle(string $title): void
    {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('news title cannot be empty');
        }
    }

    private function guardText(string $text): void
    {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('news text cannot be empty');
        }
    }

    private function guardImageUrl(string $url): void
    {
        if ('' === trim($url)) {
            throw new InvalidArgumentException('news image url cannot be empty');
        }
    }

    private function validate(): void
    {
        $this->guardTitle($this->title);
        $this->guardText($this->text);

        if (null !== $this->imageUrl) {
            $this->guardImageUrl($this->imageUrl);
        }
    }

    private function addNewsCreatedEventIfNeeded(bool $isEmitCreatedEvent): void
    {
        if ($isEmitCreatedEvent) {
            $this->events[] = new NewsCreatedEvent($this->id, $this->createdAt);
        }
    }
}
