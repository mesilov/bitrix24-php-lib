<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class Handler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->debug('Bitrix24Accounts.InstallStart.start', [
            'id' => $command->uuid->toRfc4122(),
            'domain_url' => $command->domainUrl,
            'member_id' => $command->memberId,
        ]);

        $bitrix24Account = new Bitrix24Account(
            $command->uuid,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domainUrl,
            Bitrix24AccountStatus::new,
            $command->authToken,
            new CarbonImmutable(),
            new CarbonImmutable(),
            $command->applicationVersion,
            $command->applicationScope,
            true
        );
        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush();

        foreach ($bitrix24Account->emitEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->logger->debug('Bitrix24Accounts.InstallStart.Finish');
    }
}
