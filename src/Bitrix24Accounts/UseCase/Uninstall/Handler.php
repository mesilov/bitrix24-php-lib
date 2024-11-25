<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class Handler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws Bitrix24AccountNotFoundException
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        var_dump('handle');
        $aggregateRoot = new AggregateRoot();
        $this->logger->debug('Bitrix24Accounts.Uninstall.start', [
            'b24_application_token' => $command->applicationToken,
        ]);

        /**
         * @var AggregateRootEventsEmitterInterface[]|Bitrix24AccountInterface[] $accounts
         */
        $accounts = $this->bitrix24AccountRepository->findByApplicationToken($command->applicationToken);

        foreach ($accounts as $account) {
            $account->applicationUninstalled($command->applicationToken);
            $this->bitrix24AccountRepository->save($account);
            $this->flusher->flush();

            foreach ($account->emitEvents() as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }

        $this->logger->debug('Bitrix24Accounts.Uninstall.Finish');
    }
}
