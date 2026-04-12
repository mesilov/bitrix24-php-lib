<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Delete;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Command to delete global application setting.
 */
readonly class Command
{
    public function __construct(
        public Uuid $applicationInstallationId,
        public string $key
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('Setting key cannot be empty');
        }
    }
}
