<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Create;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Command to create new application setting.
 *
 * Settings can be:
 * - Global (both b24UserId and b24DepartmentId are null)
 * - Personal (b24UserId is set)
 * - Departmental (b24DepartmentId is set)
 */
readonly class Command
{
    public function __construct(
        public Uuid $applicationInstallationId,
        public string $key,
        public string $value,
        public bool $isRequired = false,
        public ?int $b24UserId = null,
        public ?int $b24DepartmentId = null,
        public ?int $changedByBitrix24UserId = null
    ) {
        $this->validate();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('Setting key cannot be empty');
        }

        if (strlen($this->key) > 255) {
            throw new InvalidArgumentException('Setting key cannot exceed 255 characters');
        }

        // Key should contain only lowercase latin letters and dots
        if (in_array(preg_match('/^[a-z.]+$/', $this->key), [0, false], true)) {
            throw new InvalidArgumentException(
                'Setting key can only contain lowercase latin letters and dots'
            );
        }

        if (null !== $this->b24UserId && $this->b24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 user ID must be positive integer');
        }

        if (null !== $this->b24DepartmentId && $this->b24DepartmentId <= 0) {
            throw new InvalidArgumentException('Bitrix24 department ID must be positive integer');
        }

        if (null !== $this->b24UserId && null !== $this->b24DepartmentId) {
            throw new InvalidArgumentException(
                'Setting cannot be both personal and departmental. Choose one scope.'
            );
        }
    }
}
