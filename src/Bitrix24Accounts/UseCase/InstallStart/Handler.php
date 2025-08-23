<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Accounts.InstallStart.start', [
            'domain' => $command->domain,
            'member_id' => $command->memberId,
        ]);

        $uuidV7 = Uuid::v7();

        $bitrix24Account = new Bitrix24Account(
            $uuidV7,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domain->value,
            $command->authToken,
            $command->applicationVersion,
            $command->applicationScope,
            false,
            true
        );

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $this->logger->info(
            'Bitrix24Accounts.InstallStart.Finish',
            [
                'domain_url' => $command->domain,
                'member_id' => $command->memberId,
            ]
        );
    }
}
