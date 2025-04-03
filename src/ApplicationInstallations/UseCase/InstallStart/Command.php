<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
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
        public ?string $comment,
        public Uuid $bitrix24AccountUuid,
        public int $bitrix24UserId,
        public bool $isBitrix24UserAdmin,
        public string $memberId,
        public Domain $domain,
        public AuthToken $authToken,
        public int $applicationVersion,
        public Scope $applicationScope
    ) {
        $this->validate();
    }


    private function validate(): void
    {
        if ($this->portalUsersCount <= 0) {
            throw new \InvalidArgumentException('Portal Users count must be a positive integer.');
        }

        if ('' === $this->externalId) {
            throw new \InvalidArgumentException('External ID must be a non-empty string.');
        }

        if ('' === $this->comment) {
            throw new \InvalidArgumentException('Comment must be a non-empty string.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if ('' === $this->memberId) {
            throw new \InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ($this->applicationVersion <= 0) {
            throw new \InvalidArgumentException('Application version must be a positive integer.');
        }
    }
}
