<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

/**
 * Команда вызывается в тех случаях если установка происходит с помощью UI.
 * Bitrix24 отправляет событие ONAPPINSTALL на /event-handler.php и передает application_token.
 * Цель этого события донести токен приложения.
 */
readonly class Command
{
    public function __construct(
        public string $memberId,
        public Domain $domainUrl,
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

        if ('' === $this->applicationToken) {
            throw new \InvalidArgumentException('ApplicationToken must be a non-empty string.');
        }

        if ('' === $this->applicationStatus) {
            throw new \InvalidArgumentException('ApplicationStatus must be a non-empty string.');
        }
    }
}
