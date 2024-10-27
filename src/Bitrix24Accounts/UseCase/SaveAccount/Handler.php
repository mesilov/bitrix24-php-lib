<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\SaveAccount;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\SaveAccount\Command;

final readonly class Handler
{
    public function __construct(
        private Flusher $flusher,
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
    ) {}

    public function handle(Command $command):void
    {
       $this->bitrix24AccountRepository->save($command->bitrix24AccountRepository);

       $this->flusher->flush();
    }
}