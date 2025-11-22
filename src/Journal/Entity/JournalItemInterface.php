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
 * Journal item interface for SDK contract extraction
 */
interface JournalItemInterface
{
    public function getId(): Uuid;

    public function getApplicationInstallationId(): Uuid;

    public function getCreatedAt(): CarbonImmutable;

    public function getLevel(): LogLevel;

    public function getMessage(): string;

    public function getContext(): JournalContext;

    /**
     * PSR-3 compatible factory methods
     */
    public static function emergency(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function alert(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function critical(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function error(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function warning(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function notice(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function info(Uuid $applicationInstallationId, string $message, array $context = []): self;

    public static function debug(Uuid $applicationInstallationId, string $message, array $context = []): self;
}
