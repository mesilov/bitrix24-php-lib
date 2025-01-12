<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Accounts.Uninstall.start', [
            'b24_application_token' => $command->applicationToken,
        ]);

        /** @var AggregateRootEventsEmitterInterface[]|Bitrix24AccountInterface[] $accounts */
        $accounts = $this->bitrix24AccountRepository->findByApplicationToken($command->applicationToken);
        $accountsCount = count($accounts);
        foreach ($accounts as $account) {
            $account->applicationUninstalled($command->applicationToken);
            $this->bitrix24AccountRepository->save($account);
        }
        $this->flusher->flush(...$accounts);

        $this->logger->info(
            'Bitrix24Accounts.Uninstall.Finish',
            [
                'accountsCount' => $accountsCount,
                'b24_application_token' => $command->applicationToken,
            ]
        );
    }
}
