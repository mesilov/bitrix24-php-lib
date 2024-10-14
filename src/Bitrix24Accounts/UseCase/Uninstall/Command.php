<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public string $applicationToken
    )
    {
    }
}