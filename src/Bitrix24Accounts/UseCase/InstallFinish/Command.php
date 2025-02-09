<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

readonly class Command
{
    public string $domain;

    public function __construct(
        public string $applicationToken,
        public string $memberId,
        Domain $domain,
        public int $bitrix24UserId,
    ) {
        $this->validate();
        $this->domain = $domain->getValue();
    }

    private function validate(): void
    {
        if ('' === $this->applicationToken) {
            throw new \InvalidArgumentException('Application token cannot be empty.');
        }

        if ('' === $this->memberId || '0' === $this->memberId) {
            throw new \InvalidArgumentException('Member ID cannot be empty.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }
    }
}
