<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
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
        $this->logger->info('Bitrix24Accounts.ChangeDomainUrl.start', [
            'b24_domain_url_old' => $command->oldDomainUrlHost,
            'b24_domain_url_new' => $command->newDomainUrlHost,
        ]);

        /** @var AggregateRootEventsEmitterInterface[]|Bitrix24AccountInterface[] $accounts */
        $accounts = $this->bitrix24AccountRepository->findByDomain($command->oldDomainUrlHost);
        foreach ($accounts as $account) {
            $account->changeDomainUrl($command->newDomainUrlHost);
            $this->bitrix24AccountRepository->save($account);
        }

        // используется как оператор распаковки (splat operator) для передачи массива как отдельных аргументов:
        $this->flusher->flush(...$accounts);

        $this->logger->info(
            'Bitrix24Accounts.ChangeDomainUrl.Finish',
            [
                'b24_domain_url_old' => $command->oldDomainUrlHost,
                'b24_domain_url_new' => $command->newDomainUrlHost,
            ]
        );
    }
}
