<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

readonly class MarkOldInstallationsConfig
{
    public function __construct(
        public int $ttlInSeconds,
    ) {
        if ($this->ttlInSeconds < 0) {
            throw new InvalidArgumentException('TTL in seconds must be a non-negative integer.');
        }
    }
}
