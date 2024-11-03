<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class Handler
{
    public function __construct(
        private EventDispatcherInterface           $eventDispatcher,
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher                            $flusher,
        private LoggerInterface                    $logger
    )
    {
    }

    /**
     * @throws MultipleBitrix24AccountsFoundException
     */
    public function handle(Command $command): void
    {
        $this->logger->debug('Bitrix24Accounts.RenewAuthToken.start', [
            'domain_url' => $command->renewedAuthToken->domain,
            'member_id' => $command->renewedAuthToken->memberId,
            'bitrix24_user_id' => $command->bitrix24UserId
        ]);

        // get all active bitrix24 accounts
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $command->renewedAuthToken->memberId,
            Bitrix24AccountStatus::active,
            $command->bitrix24UserId
        );

        if ($command->bitrix24UserId === null && count($accounts) > 1) {
            throw new MultipleBitrix24AccountsFoundException(
                sprintf('updating auth token failure - for domain %s with member id %s found multiple active accounts, try pass bitrix24_user_id in command',
                    $command->renewedAuthToken->domain,
                    $command->renewedAuthToken->memberId
                )
            );
        }

        if ($command->bitrix24UserId !== null && count($accounts) > 1) {
            throw new MultipleBitrix24AccountsFoundException(
                sprintf('updating auth token failure - for domain %s with member id %s and bitrix24 user id %s found multiple active accounts',
                    $command->renewedAuthToken->domain,
                    $command->renewedAuthToken->memberId,
                    $command->bitrix24UserId
                )
            );
        }

        $targetAccount = $accounts[0];
        /**
         * @var Bitrix24AccountInterface|AggregateRootEventsEmitterInterface $targetAccount
         */
        $targetAccount->renewAuthToken($command->renewedAuthToken);
        $this->bitrix24AccountRepository->save($targetAccount);
        $this->flusher->flush();
        foreach ($targetAccount->emitEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->logger->debug('Bitrix24Accounts.RenewAuthToken.finish');
    }
}