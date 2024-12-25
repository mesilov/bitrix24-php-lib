<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Symfony\Component\Uid\Uuid;
use InvalidArgumentException;

readonly class Command
{
    public function __construct(
        public Uuid      $uuid,
        public int       $bitrix24UserId,
        public bool      $isBitrix24UserAdmin,
        public string    $memberId,
        public string    $domainUrl,
        public AuthToken $authToken,
        public int       $applicationVersion,
        public Scope     $applicationScope
    )
    {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->uuid) || !Uuid::isValid($this->uuid->toString())) {
            throw new InvalidArgumentException('Empty uuid or invalid UUID provided.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }

        if (!is_string($this->memberId) || empty($this->memberId)) {
            throw new InvalidArgumentException('Member ID must be a non-empty string.');
        }

      /*  if (!filter_var($this->domainUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Domain URL is not valid.');
        }*/

        if ($this->applicationVersion <= 0) {
            throw new InvalidArgumentException('Application version must be a positive integer.');
        }

        if (!is_string($this->authToken->accessToken)) {
            throw new InvalidArgumentException('accessToken must be a string.');
        }
    }
}
