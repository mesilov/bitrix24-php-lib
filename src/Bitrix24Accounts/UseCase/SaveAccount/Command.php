<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\SaveAccount;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;

readonly class Command
{
    public function __construct(
        public Bitrix24AccountRepositoryInterface $bitrix24AccountRepository
    ){}
}