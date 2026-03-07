<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\Lib\ApplicationSettings\Events\ApplicationSettingsItemChangedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Application setting entity.
 *
 * Stores key-value settings for application installations.
 * Settings can be:
 * - Global (for entire application installation)
 * - Personal (tied to specific Bitrix24 user)
 * - Departmental (tied to specific department)
 */
class ApplicationSettingsItem extends AggregateRoot implements ApplicationSettingsItemInterface
{
    private readonly Uuid $id;

    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    public function __construct(
        private readonly Uuid $applicationInstallationId,
        private readonly string $key,
        private string $value,
        private readonly bool $isRequired = false,
        private readonly ?int $b24UserId = null,
        private readonly ?int $b24DepartmentId = null,
        private ?int $changedByBitrix24UserId = null,
        private ApplicationSettingStatus $status = ApplicationSettingStatus::Active
    ) {
        $this->id = Uuid::v7();
        $this->validateKey($key);
        $this->validateValue();
        $this->validateScope($b24UserId, $b24DepartmentId);
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[\Override]
    public function getApplicationInstallationId(): Uuid
    {
        return $this->applicationInstallationId;
    }

    #[\Override]
    public function getKey(): string
    {
        return $this->key;
    }

    #[\Override]
    public function getValue(): string
    {
        return $this->value;
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
    public function getB24UserId(): ?int
    {
        return $this->b24UserId;
    }

    #[\Override]
    public function getB24DepartmentId(): ?int
    {
        return $this->b24DepartmentId;
    }

    #[\Override]
    public function getChangedByBitrix24UserId(): ?int
    {
        return $this->changedByBitrix24UserId;
    }

    #[\Override]
    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    #[\Override]
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Mark setting as deleted (soft delete).
     */
    #[\Override]
    public function markAsDeleted(): void
    {
        if (ApplicationSettingStatus::Deleted === $this->status) {
            return; // Already deleted
        }

        $this->status = ApplicationSettingStatus::Deleted;
        $this->updatedAt = new CarbonImmutable();
    }

    /**
     * Update setting value.
     */
    #[\Override]
    public function updateValue(string $value, ?int $changedByBitrix24UserId = null): void
    {
        $this->validateValue();

        if ($this->value !== $value) {
            $oldValue = $this->value;
            $this->value = $value;
            $this->changedByBitrix24UserId = $changedByBitrix24UserId;
            $this->updatedAt = new CarbonImmutable();

            // Emit event about setting change
            $this->events[] = new ApplicationSettingsItemChangedEvent(
                $this->id,
                $this->key,
                $oldValue,
                $value,
                $changedByBitrix24UserId,
                $this->updatedAt
            );
        }
    }

    /**
     * Check if setting is global (not tied to user or department).
     */
    #[\Override]
    public function isGlobal(): bool
    {
        return null === $this->b24UserId && null === $this->b24DepartmentId;
    }

    /**
     * Check if setting is personal (tied to specific user).
     */
    #[\Override]
    public function isPersonal(): bool
    {
        return null !== $this->b24UserId;
    }

    /**
     * Check if setting is departmental (tied to specific department).
     */
    #[\Override]
    public function isDepartmental(): bool
    {
        return null !== $this->b24DepartmentId && null === $this->b24UserId;
    }

    /**
     * Validate setting key
     * Only lowercase latin letters and dots are allowed, max 255 characters.
     */
    private function validateKey(string $key): void
    {
        if ('' === trim($key)) {
            throw new InvalidArgumentException('Setting key cannot be empty');
        }

        if (strlen($key) > 255) {
            throw new InvalidArgumentException('Setting key cannot exceed 255 characters');
        }

        // Key should contain only lowercase latin letters and dots
        if (in_array(preg_match('/^[a-z.]+$/', $key), [0, false], true)) {
            throw new InvalidArgumentException(
                'Setting key can only contain lowercase latin letters and dots'
            );
        }
    }

    /**
     * Validate scope parameters.
     */
    private function validateScope(?int $b24UserId, ?int $b24DepartmentId): void
    {
        if (null !== $b24UserId && $b24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 user ID must be positive integer');
        }

        if (null !== $b24DepartmentId && $b24DepartmentId <= 0) {
            throw new InvalidArgumentException('Bitrix24 department ID must be positive integer');
        }

        // User and department cannot be set simultaneously
        if (null !== $b24UserId && null !== $b24DepartmentId) {
            throw new InvalidArgumentException(
                'Setting cannot be both personal and departmental. Choose one scope.'
            );
        }
    }

    /**
     * Validate setting value.
     */
    private function validateValue(): void
    {
        // Value can be empty but not null (handled by type hint)
        // We store value as string, could be JSON or plain text
        // No specific validation needed here, can be extended if needed
    }
}
