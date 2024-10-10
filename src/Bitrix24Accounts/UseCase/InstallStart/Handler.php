<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Carbon\CarbonImmutable;
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
        $this->logger->debug('Bitrix24Accounts.InstallStart.start', [
            'id' => $command->uuid->toRfc4122(),
            'domain_url' => $command->domainUrl,
            'member_id' => $command->memberId
        ]);

        $this->bitrix24AccountRepository->save(
            new Bitrix24Account(
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
                $command->applicationScope
            )
        );
        $this->eventDispatcher->dispatch(
            new Bitrix24AccountCreatedEvent(
                $command->uuid,
                new CarbonImmutable()
            )
        );
        $this->logger->debug('Bitrix24Accounts.InstallStart.Finish');
    }
}