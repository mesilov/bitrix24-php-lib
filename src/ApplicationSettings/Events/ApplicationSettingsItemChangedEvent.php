<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Events;

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Event emitted when application setting value is changed.
 *
 * Contains information about:
 * - Which setting was changed
 * - Old and new values
 * - Who changed it (optional)
 */
readonly class ApplicationSettingsItemChangedEvent
{
    public function __construct(
        public Uuid $settingId,
        public string $key,
        public string $oldValue,
        public string $newValue,
        public ?int $changedByBitrix24UserId,
        public CarbonImmutable $changedAt
    ) {}
}
