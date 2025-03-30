<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\InstallStart;

use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $uuid,
        public Uuid $bitrix24AccountId,
        public ApplicationStatus $applicationStatus,
        public PortalLicenseFamily $portalLicenseFamily,
        public ?int $portalUsersCount,
        public ?Uuid $contactPersonId,
        public ?Uuid $bitrix24PartnerContactPersonId,
        public ?Uuid $bitrix24PartnerId,
        public ?string $externalId,
        public ?string $comment
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->portalUsersCount <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if ('' === $this->externalId) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ('' === $this->comment) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }
    }
}
