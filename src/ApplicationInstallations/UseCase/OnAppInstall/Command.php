<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

readonly class Command
{
    public function __construct(
        public string $memberId,
        public string $domainUrl,
        public string $applicationToken,
        public string $applicationStatus,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === $this->memberId) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ('' === $this->domainUrl) {
            throw new \InvalidArgumentException('Domain url must be a non-empty string.');
        }

        if ('' === $this->applicationToken) {
            throw new \InvalidArgumentException('ApplicationToken must be a non-empty string.');
        }

        if ('' === $this->applicationStatus) {
            throw new \InvalidArgumentException('ApplicationStatus must be a non-empty string.');
        }
    }
}
