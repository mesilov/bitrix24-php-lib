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
        if ($this->applicationToken === '' || $this->applicationToken === '0') {
            throw new \InvalidArgumentException('Empty application token application token.');
        }
    }
}
