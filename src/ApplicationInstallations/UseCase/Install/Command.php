<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Installation can occur in 2 scenes.
 * If $ ApplicationToken is transferred to this team means this installation without UI.
 * Otherwise, this installation with UI and $ ApplicationtoKen should not be transmitted.
 *
 * 1) UI - the user launches the installation through the interface.
 * Bitrix24 sends the initial request to /install.php with parameters:
 * Auth_id, refresh_id, member_id in the body of the request. Without application_token
 * The system creates Bitrix24ACCOUNT in the status of New.
 * Bitrix24 sends the OnppinStall event to /Event-handler.php and transfers Application_Token
 * ApplicationInstalled ($ applicationToken) is called up with the resulting token.
 * 2) Without UI - the installation is initiated by a direct post -call with a full set of Credentials.
 * Bitrix24ACCOUNT is immediately created. In the installer and in the account, the method with the transferred token is called:
 * $ BITRIX24ACCOUNT-> ApplicationInstalled ($ applicationToken);
 * $ ApplicationinStallation-> ApplicationInstalled ($ applicationToken);
 */
readonly class Command
{
    public function __construct(
        public string $memberId,
        public Domain $domain,
        public AuthToken $authToken,
        public int $applicationVersion,
        public Scope $applicationScope,
        public int $bitrix24UserId,
        public bool $isBitrix24UserAdmin,
        public ApplicationStatus $applicationStatus,
        public PortalLicenseFamily $portalLicenseFamily,
        public ?string $applicationToken = null,
        public ?int $portalUsersCount = null,
        public ?Uuid $contactPersonId = null,
        public ?Uuid $bitrix24PartnerContactPersonId = null,
        public ?Uuid $bitrix24PartnerId = null,
        public ?string $externalId = null,
        public ?string $comment = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->portalUsersCount <= 0) {
            throw new InvalidArgumentException('Portal Users count must be a positive integer.');
        }

        if ('' === $this->externalId) {
            throw new InvalidArgumentException('External ID must be a non-empty string.');
        }

        if ('' === $this->comment) {
            throw new InvalidArgumentException('Comment must be a non-empty string.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if ('' === $this->memberId) {
            throw new InvalidArgumentException('Member ID must be a non-empty string.');
        }

        if ($this->applicationVersion <= 0) {
            throw new InvalidArgumentException('Application version must be a positive integer.');
        }

        if (null !== $this->applicationToken && '' === trim($this->applicationToken)) {
            throw new InvalidArgumentException('Application token must be a non-empty string.');
        }
    }
}
