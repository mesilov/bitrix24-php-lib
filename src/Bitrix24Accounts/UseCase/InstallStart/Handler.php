<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

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
            'id' => $command->uuid->toRfc4122(),
            'domain' => $command->domain,
            'member_id' => $command->memberId,
        ]);

        $bitrix24Account = new Bitrix24Account(
            $command->uuid,
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

        $isAccountExists = $this->bitrix24AccountRepository->existsById($bitrix24Account->getId());

        if (!$isAccountExists) {
            $this->bitrix24AccountRepository->save($bitrix24Account);
            $this->flusher->flush($bitrix24Account);

            $this->logger->info(
                'Bitrix24Accounts.InstallStart.Finish',
                [
                    'id' => $command->uuid->toRfc4122(),
                    'domain_url' => $command->domain,
                    'member_id' => $command->memberId,
                ]
            );
        } else {
            $this->logger->info(
                'Bitrix24Accounts.InstallStart.AlreadyExists',
                [
                    'id' => $command->uuid->toRfc4122(),
                    'domain' => $command->domain,
                    'member_id' => $command->memberId,
                ]
            );

            throw new Bitrix24AccountNotFoundException(
                sprintf('bitrix24account with uuid "%s" already exists', $command->uuid->toRfc4122())
            );
        }
    }
}
