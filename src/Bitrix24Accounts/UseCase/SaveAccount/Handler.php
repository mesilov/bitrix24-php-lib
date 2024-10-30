<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\SaveAccount;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;

final readonly class Handler
{
    public function __construct(
        private Flusher $flusher,
        private Bitrix24AccountRepositoryInterface $repository,
    ) {}

    public function handle(Command $command):void
    {
       $this->repository->save($command->bitrix24Account);

       $this->flusher->flush();
    }
}