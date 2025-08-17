<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

/**
 * Command is called when installation occurs through UI.
 * Bitrix24 sends ONAPPINSTALL event to /event-handler.php and passes application_token.
 * The purpose of this event is to deliver the application token.
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
