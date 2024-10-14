<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\InstallFinish;

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
        $this->logger->debug('Bitrix24Accounts.InstallFinish.start', [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
            'b24_user_id' => $command->bitrix24UserId
        ]);

        //todo discuss are we need add bitrix24UserId in contract?
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $command->memberId,
            Bitrix24AccountStatus::new
        );
        if ($accounts === []) {
            throw new Bitrix24AccountNotFoundException(sprintf(
                'bitrix24 account for domain %s with member id %s in status «new» not found',
                $command->domainUrl,
                $command->memberId
            ));
        }

        if (count($accounts) > 1) {
            throw new MultipleBitrix24AccountsFoundException(sprintf(
                'multiple bitrix24 accounts for domain %s with member id %s in status «new» found',
                $command->domainUrl,
                $command->memberId
            ));
        }

        $targetAccount = $accounts[0];
        /**
         * @var Bitrix24AccountInterface|AggregateRootEventsEmitterInterface $targetAccount
         */
        $targetAccount->applicationInstalled($command->applicationToken);

        $this->bitrix24AccountRepository->save($targetAccount);
        foreach ($targetAccount->emitEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->logger->debug('Bitrix24Accounts.InstallFinish.Finish');
    }
}