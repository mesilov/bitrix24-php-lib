<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Symfony\Component\Uid\Uuid;

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
    }
}