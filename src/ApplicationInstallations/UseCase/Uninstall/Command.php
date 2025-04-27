<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

readonly class Command
{
    public function __construct(
        public string $applicationToken,
    )
    {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === $this->applicationToken) {
            throw new \InvalidArgumentException('applicationToken must be a non-empty string.');
        }

    }
}