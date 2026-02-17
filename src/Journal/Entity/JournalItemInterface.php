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

namespace Bitrix24\Lib\Journal\Entity;

use Bitrix24\Lib\Journal\ValueObjects\JournalContext;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Journal item interface for SDK contract extraction.
 */
interface JournalItemInterface
{
    public function getId(): Uuid;

    public function getApplicationInstallationId(): Uuid;

    public function getMemberId(): string;

    public function getCreatedAt(): CarbonImmutable;

    public function getLevel(): LogLevel;

    public function getMessage(): string;

    public function getContext(): JournalContext;
}
