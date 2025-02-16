<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall;

readonly class Command
{
    public function __construct(
        /**
         * @var non-empty-string $applicationToken
         */
        public string $applicationToken
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === $this->applicationToken) {
            throw new \InvalidArgumentException('Application token must be a non-empty string.');
        }
    }
}
