<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;

readonly class Command
{
    public function __construct(
        public int $bitrix24UserId,
        public bool $isBitrix24UserAdmin,
        public string $memberId,
        public Domain $domain,
        public AuthToken $authToken,
        public int $applicationVersion,
        public Scope $applicationScope
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if ('' === $this->memberId) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ($this->applicationVersion <= 0) {
            throw new \InvalidArgumentException('Application version must be a positive integer.');
        }
    }
}
