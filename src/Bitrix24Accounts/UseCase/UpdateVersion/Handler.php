<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\UpdateVersion;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws MultipleBitrix24AccountsFoundException
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Accounts.UpdateVersion.start', [
            'uuid' => $command->uuid,
            'bitrix24_user_id' => $command->bitrix24UserId,
            'is_bitrix24UserAdmin' => $command->isBitrix24UserAdmin,
            'member_id' => $command->memberId,
            'auth_token' => $command->authToken,
            'new_application_version' => $command->newApplicationVersion,
            'new_application_scope' => $command->newApplicationScope,
        ]);

        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $command->memberId,
            Bitrix24AccountStatus::active,
            $command->bitrix24UserId,
        );

        if ([] !== $accounts) {
            /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface $bitrix24Account */
            $bitrix24Account = $accounts[0];
            $bitrix24Account->updateApplicationVersion(
                $command->authToken,
                $command->bitrix24UserId,
                $command->newApplicationVersion,
                $command->newApplicationScope,
            );

            $this->bitrix24AccountRepository->save($bitrix24Account);
            $this->flusher->flush($bitrix24Account);

            $this->logger->info('Bitrix24Accounts.UpdateVersion.finish', [
                'uuid' => $command->uuid,
                'bitrix24_user_id' => $command->bitrix24UserId,
                'is_bitrix24UserAdmin' => $command->isBitrix24UserAdmin,
                'member_id' => $command->memberId,
                'auth_token' => $command->authToken,
                'new_application_version' => $command->newApplicationVersion,
                'new_application_scope' => $command->newApplicationScope,
            ]);
        } else {
            $this->logger->info(
                'Bitrix24Accounts.UpdateVersion.NotFoundAccount',
                [
                    'uuid' => $command->uuid,
                    'bitrix24_user_id' => $command->bitrix24UserId,
                    'is_bitrix24UserAdmin' => $command->isBitrix24UserAdmin,
                    'member_id' => $command->memberId,
                    'auth_token' => $command->authToken,
                    'new_application_version' => $command->newApplicationVersion,
                    'new_application_scope' => $command->newApplicationScope,
                ]
            );

            throw new MultipleBitrix24AccountsFoundException(
                sprintf(
                    'bitrix24account not found by memberId %s, status %s and bitrix24UserId %s ',
                    $command->memberId,
                    'active',
                    $command->bitrix24UserId
                )
            );
        }
    }
}
