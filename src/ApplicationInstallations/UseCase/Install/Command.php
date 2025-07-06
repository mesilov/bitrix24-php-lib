<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Symfony\Component\Uid\Uuid;

readonly class Command implements \Stringable
{
    public function __construct(
        public ApplicationStatus $applicationStatus,
        public PortalLicenseFamily $portalLicenseFamily,
        public ?int $portalUsersCount,
        public ?Uuid $contactPersonId,
        public ?Uuid $bitrix24PartnerContactPersonId,
        public ?Uuid $bitrix24PartnerId,
        public ?string $externalId,
        public ?string $comment,
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

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            ' portalUsersCount: %s, contactPersonId: %s, bitrix24PartnerContactPersonId: %s,
             bitrix24PartnerId: %s, externalId: %s, comment: %s, bitrix24UserId: %d,
              isBitrix24UserAdmin: %s, memberId: %s',
            $this->portalUsersCount ?? 'null',
            $this->contactPersonId ?? 'null',
            $this->bitrix24PartnerContactPersonId ?? 'null',
            $this->bitrix24PartnerId ?? 'null',
            $this->externalId ?? 'null',
            $this->comment ?? 'null',
            $this->bitrix24UserId,
            $this->isBitrix24UserAdmin ? 'true' : 'false',
            $this->memberId
        );
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
