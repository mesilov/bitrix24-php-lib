<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

readonly class Command
{
    public function __construct(
        public string $applicationToken,
        public string $memberId,
        public string $domainUrl,
        public ?int $bitrix24UserId,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->applicationToken)) {
            throw new \InvalidArgumentException('Application token cannot be empty.');
        }
        if (empty($this->memberId)) {
            throw new \InvalidArgumentException('Member ID cannot be empty.');
        }
        if (!filter_var($this->domainUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Domain URL is not valid.');
        }
    }
}
