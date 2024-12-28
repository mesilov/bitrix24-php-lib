<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;
use Symfony\Component\Uid\Uuid;
use InvalidArgumentException;
readonly class Command
{
    public function __construct(
        public RenewedAuthToken $renewedAuthToken,
        public ?int $bitrix24UserId = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->bitrix24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }
    }
}
