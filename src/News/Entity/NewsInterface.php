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

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Interface for News entity.
 */
interface NewsInterface
{
    public function getId(): Uuid;

    public function getTitle(): string;

    public function getText(): string;

    public function getStatus(): NewsStatus;

    public function getCreatedAt(): CarbonImmutable;

    public function getUpdatedAt(): CarbonImmutable;

    public function changeTitle(string $title): void;

    public function changeText(string $text): void;

    public function publish(): void;

    public function revertToDraft(): void;

    public function archive(): void;

    public function markAsDeleted(): void;
}
