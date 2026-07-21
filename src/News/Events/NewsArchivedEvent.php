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

namespace Bitrix24\Lib\News\Events;

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\Event;

class NewsArchivedEvent extends Event
{
    public function __construct(
        public readonly Uuid $newsId,
        public readonly CarbonImmutable $timestamp
    ) {}
}
