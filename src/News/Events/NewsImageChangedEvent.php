<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\Events;

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Generated when a news item's image is attached, replaced, or detached.
 *
 * - imageUrl: the new image URL (null if detached)
 * - oldImageUrl: the previous image URL (null if there was no image before)
 *
 * Use oldImageUrl to clean up the previous file from storage if needed.
 */
class NewsImageChangedEvent extends Event
{
    public function __construct(
        public readonly Uuid $newsId,
        public readonly CarbonImmutable $timestamp,
        public readonly ?string $imageUrl,
        public readonly ?string $oldImageUrl = null
    ) {}
}
