<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\News\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\Lib\News\Events\NewsArchivedEvent;
use Bitrix24\Lib\News\Events\NewsCreatedEvent;
use Bitrix24\Lib\News\Events\NewsDeletedEvent;
use Bitrix24\Lib\News\Events\NewsPublishedEvent;
use Bitrix24\Lib\News\Events\NewsRevertedToDraftEvent;
use Bitrix24\Lib\News\Events\NewsTextChangedEvent;
use Bitrix24\Lib\News\Events\NewsTitleChangedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

class News extends AggregateRoot implements NewsInterface
{
    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    private NewsStatus $status = NewsStatus::draft;

    public function __construct(
        private readonly Uuid $id,
        private string $title,
        private string $text,
        private readonly bool $isEmitNewsCreatedEvent = false,
    ) {
        $this->validate();
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();

        $this->addNewsCreatedEventIfNeeded($this->isEmitNewsCreatedEvent);
    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[\Override]
    public function getTitle(): string
    {
        return $this->title;
    }

    #[\Override]
    public function getText(): string
    {
        return $this->text;
    }

    #[\Override]
    public function getStatus(): NewsStatus
    {
        return $this->status;
    }

    #[\Override]
    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    #[\Override]
    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    #[\Override]
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

    #[\Override]
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

    #[\Override]
    public function publish(): void
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
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsPublishedEvent($this->id, $this->updatedAt);
    }

    #[\Override]
    public function revertToDraft(): void
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
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsRevertedToDraftEvent($this->id, $this->updatedAt);
    }

    #[\Override]
    public function archive(): void
    {
        if (NewsStatus::archived === $this->status) {
            throw new LogicException('news already in status «archived»');
        }

        $this->status = NewsStatus::archived;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new NewsArchivedEvent($this->id, $this->updatedAt);
    }

    #[\Override]
    public function markAsDeleted(): void
    {
        if (NewsStatus::deleted === $this->status) {
            throw new LogicException('news already in status «deleted»');
        }

        $this->status = NewsStatus::deleted;
        $this->updatedAt = new CarbonImmutable();

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

    private function validate(): void
    {
        $this->guardTitle($this->title);
        $this->guardText($this->text);
    }

    private function addNewsCreatedEventIfNeeded(bool $isEmitCreatedEvent): void
    {
        if ($isEmitCreatedEvent) {
            $this->events[] = new NewsCreatedEvent($this->id, $this->createdAt);
        }
    }
}
