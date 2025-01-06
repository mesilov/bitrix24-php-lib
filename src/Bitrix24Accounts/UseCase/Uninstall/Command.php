<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

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
        if (empty($this->applicationToken) || !Uuid::isValid($this->applicationToken)) {
            throw new InvalidArgumentException('Empty application token or invalid application token.');
        }
    }
}
