<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Lib\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class Handler
{
    public function __construct(
        private EventDispatcherInterface           $eventDispatcher,
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private LoggerInterface                    $logger
    )
    {
    }

    /**
     * @throws Bitrix24AccountNotFoundException
     */
    public function handle(Command $command): void
    {
        $this->logger->debug('Bitrix24Accounts.RenewAuthToken.start', [
            'domain_url' => $command->renewedAuthToken->domain,
            'member_id' => $command->renewedAuthToken->memberId,
            'bitrix24_user_id' => $command->bitrix24UserId
        ]);

        // get all active bitrix24 accounts
        //todo discuss add bitrix24_user_id in contract?
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $command->renewedAuthToken->memberId,
            Bitrix24AccountStatus::active
        );

        if ($command->bitrix24UserId === null && count($accounts) > 1) {
            //todo discuss move to b24phpsdk contracts?
            throw new MultipleBitrix24AccountsFoundException(
                sprintf('updating auth token failure - for domain %s with member id %s found multiple active accounts, try pass bitrix24_user_id in command',
                    $command->renewedAuthToken->domain,
                    $command->renewedAuthToken->memberId
                )
            );
        }

        // filter by member_id and bitrix24_user_id
        if ($command->bitrix24UserId !== null && count($accounts) > 1) {

            // try to find target bitrix24 account
            $bitrix24UserId = $command->bitrix24UserId;
            $targetAccount = array_filter($accounts, static fn($account): bool => $account->getBitrix24UserId() === $bitrix24UserId);
            // Reset array keys and get the first matched account (if any)
            $targetAccount = $targetAccount !== [] ? reset($targetAccount) : null;

            if ($targetAccount===null) {
                throw new Bitrix24AccountNotFoundException(sprintf('account with %s domain %s memberId and %s bitrix24UserId not found',
                    $command->renewedAuthToken->domain,
                    $command->renewedAuthToken->memberId,
                    $command->bitrix24UserId,
                ));
            }
        }

        $targetAccount = $accounts[0];
        /**
         * @var Bitrix24AccountInterface|AggregateRootEventsEmitterInterface $targetAccount
         */
        $targetAccount->renewAuthToken($command->renewedAuthToken);

        $this->bitrix24AccountRepository->save($targetAccount);
        foreach ($targetAccount->emitEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->logger->debug('Bitrix24Accounts.InstallFinish');
    }
}