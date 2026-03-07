<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Entity;

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Interface for ApplicationSetting entity.
 *
 * @todo Move this interface to b24-php-sdk contracts after stabilization
 */
interface ApplicationSettingsItemInterface
{
    public function getId(): Uuid;

    public function getApplicationInstallationId(): Uuid;

    public function getKey(): string;

    public function getValue(): string;

    public function getB24UserId(): ?int;

    public function getB24DepartmentId(): ?int;

    public function getChangedByBitrix24UserId(): ?int;

    public function isRequired(): bool;

    public function isActive(): bool;

    public function getCreatedAt(): CarbonImmutable;

    public function getUpdatedAt(): CarbonImmutable;

    /**
     * Update setting value.
     */
    public function updateValue(string $value, ?int $changedByBitrix24UserId = null): void;

    /**
     * Mark setting as deleted (soft delete).
     */
    public function markAsDeleted(): void;

    /**
     * Check if setting is global (not tied to user or department).
     */
    public function isGlobal(): bool;

    /**
     * Check if setting is personal (tied to specific user).
     */
    public function isPersonal(): bool;

    /**
     * Check if setting is departmental (tied to specific department).
     */
    public function isDepartmental(): bool;
}
