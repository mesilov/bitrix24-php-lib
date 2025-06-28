<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

readonly class Command
{
    public function __construct(
        public Domain $domainUrl,
        public string $memberId,
        public string $applicationToken
    )
    {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === $this->applicationToken) {
            throw new \InvalidArgumentException('applicationToken must be a non-empty string.');
        }

        if ('' === $this->memberId) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }
    }
}