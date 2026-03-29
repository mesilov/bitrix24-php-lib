<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Entity;

/**
 * Application Setting Status enum.
 *
 * Represents the lifecycle status of an application setting.
 * Uses soft-delete pattern to maintain history and enable recovery.
 */
enum ApplicationSettingStatus: string
{
    /**
     * Active setting - available for use.
     */
    case Active = 'active';

    /**
     * Deleted setting - soft-deleted, hidden from normal queries.
     */
    case Deleted = 'deleted';

    /**
     * Check if status is active.
     */
    public function isActive(): bool
    {
        return self::Active === $this;
    }

    /**
     * Check if status is deleted.
     */
    public function isDeleted(): bool
    {
        return self::Deleted === $this;
    }
}
