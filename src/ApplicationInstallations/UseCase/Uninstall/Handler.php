<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepository $bitrix24AccountRepository,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.Uninstall.start', [
            'domainUrl' => $command->domainUrl,
            'memberId' => $command->memberId,
            'applicationToken' => $command->applicationToken,
        ]);

        /*
            * 1)Аккаунтов может быть несколько , но деинсталяция на портале проводится только 1 раз , то есть есть мастер аккаунт который нужно получать.
            * 2)У остальных аккаунтов деинсталяции быть не может это просто (доступы/авторизации).
            * 3)Может быть такой сценарий что при увольнении сотрудника особенно админа у которого была активная установка. Установка может зависнуть ,
            * таким образом нужно пробегаться по всем аккаунтам и установкам и проверять нету ли активных, если что деактивировать.
        */
        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface[] $b24Accounts */
        $b24Accounts = $this->bitrix24AccountRepository->findByMemberId($command->memberId);

        if ([] !== $b24Accounts) {
            $entitiesToFlush = [];
            foreach ($b24Accounts as $b24Account) {
                $isMaster = $b24Account->isMasterAccount();
                if ($isMaster) {
                    /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
                    $activeInstallation = $this->applicationInstallationRepository->findActiveByAccountId($b24Account->getId());
                    if (null !== $activeInstallation) {
                        $activeInstallation->applicationUninstalled($command->applicationToken);
                        $this->applicationInstallationRepository->save($activeInstallation);
                        $entitiesToFlush[] = $activeInstallation;
                    }

                    //  Тут тоже спорно получается , а если установка была по событию, а событие не произошло и токен не записался. ???
                    $b24Account->applicationUninstalled($command->applicationToken);
                } else {
                    $b24Account->applicationUninstalled(null);
                }

                $this->bitrix24AccountRepository->save($b24Account);
                $entitiesToFlush[] = $b24Account;
            }

            $this->flusher->flush(...$entitiesToFlush);
        }

        $this->logger->info(
            'ApplicationInstallations.Uninstall.Finish'
        );
    }
}
