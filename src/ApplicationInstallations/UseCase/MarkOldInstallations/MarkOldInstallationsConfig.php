<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

readonly class MarkOldInstallationsConfig
{
    public function __construct(
        public int $ttlInSeconds,
        public ?string $memberId = null,
        public bool $dryRun = false,
    ) {
        if ($this->ttlInSeconds < 0) {
            throw new InvalidArgumentException('TTL in seconds must be a non-negative integer.');
        }

        if (null !== $this->memberId && '' === trim($this->memberId)) {
            throw new InvalidArgumentException('Member ID must be a non-empty string.');
        }
    }
}
