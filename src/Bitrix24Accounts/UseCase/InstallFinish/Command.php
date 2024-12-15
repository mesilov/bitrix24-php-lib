<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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
            throw new InvalidArgumentException('Application token cannot be empty.');
        }

        if (empty($this->memberId)) {
            throw new InvalidArgumentException('Member ID cannot be empty.');
        }

        if (empty($this->domainUrl)) {
            throw new InvalidArgumentException('Domain URL cannot be empty.');
        }
    }
}
