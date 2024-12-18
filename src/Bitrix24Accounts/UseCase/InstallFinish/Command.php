<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;
use InvalidArgumentException;

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
        /*$pattern = '/^(https?:\/\/)?([a-z0-9-]+\.[a-z]{2,})(\/[^\s]*)?$/i';
        if (!preg_match($pattern, $this->domainUrl)) {
            throw new InvalidArgumentException('Domain URL is not valid.');
        }*/
        if (!filter_var($this->domainUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Domain URL is not valid.');
        }
    }
}
