<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\Events;

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\Event;

class NewsPublishedEvent extends Event
{
    public function __construct(
        public readonly Uuid $newsId,
        public readonly CarbonImmutable $timestamp
    ) {}
}
