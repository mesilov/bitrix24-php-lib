<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
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

    public function handle(Command $command): void
    {
        $this->logger->debug('Bitrix24Accounts.ChangeDomainUrl.start', [
            'b24_domain_url_old' => $command->oldDomainUrlHost,
            'b24_domain_url_new' => $command->newDomainUrlHost,
        ]);

        $accounts = $this->bitrix24AccountRepository->findByDomain($command->oldDomainUrlHost);
        foreach ($accounts as $account) {
            $account->changeDomainUrl($command->newDomainUrlHost);
            $this->bitrix24AccountRepository->save($account);
            // todo выяснить почему он не видит объединение типов
            /** @phpstan-ignore-next-line */
            foreach ($account->emitEvents() as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }

        $this->logger->debug('Bitrix24Accounts.ChangeDomainUrl.Finish');
    }
}