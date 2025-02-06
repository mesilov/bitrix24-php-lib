<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Symfony\Component\Uid\Uuid;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

readonly class Command
{
    public string $domain;
    public function __construct(
        public Uuid $uuid,
        public int $bitrix24UserId,
        public bool $isBitrix24UserAdmin,
        public string $memberId,
        Domain $domainUrl,
        public AuthToken $authToken,
        public int $applicationVersion,
        public Scope $applicationScope
    ) {
        $this->validate();
        $this->domain = $domainUrl->getValue();
    }

    private function validate(): void
    {
        if (empty($this->uuid)) {
            throw new \InvalidArgumentException('Empty UUID provided.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if (!is_string($this->memberId) || ('' === $this->memberId || '0' === $this->memberId)) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ($this->applicationVersion <= 0) {
            throw new \InvalidArgumentException('Application version must be a positive integer.');
        }
    }
}
