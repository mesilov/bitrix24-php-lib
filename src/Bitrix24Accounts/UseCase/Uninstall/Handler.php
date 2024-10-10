<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Lib\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\Uninstall\Command;
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
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->debug('Bitrix24Accounts.Uninstall.start', [
            'b24_application_token' => $command->applicationToken,
        ]);
        //todo remove after update contract in b24phpsdk
        /** @phpstan-ignore-next-line */
        $accounts = $this->bitrix24AccountRepository->findByApplicationToken($command->applicationToken);

        foreach ($accounts as $account) {
            $account->applicationUninstalled($command->applicationToken);
            $this->bitrix24AccountRepository->save($account);
            foreach ($account->emitEvents() as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }

        $this->logger->debug('Bitrix24Accounts.Uninstall.Finish');
    }
}