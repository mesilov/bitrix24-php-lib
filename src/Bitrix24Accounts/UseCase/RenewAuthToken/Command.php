<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;

readonly class Command
{
    public function __construct(
        public RenewedAuthToken $renewedAuthToken,
        public ?int             $bitrix24UserId = null,
    )
    {
    }
}