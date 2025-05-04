<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepository         $bitrix24AccountRepository,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher                           $flusher,
        private LoggerInterface                   $logger
    )
    {
    }

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.Install.start', [
            (string)$command
        ]);

        //Проверяем есть ли активные аккаунты если есть значит деактивируем аккаунты и установщики
        // 1. Получаем все аккаунты с этим memberId
        /* (В идеале же у нас только один аккаунт должен быть с memberId, но я думаю лучше все таки возвращать массив мало ли)!!!!!
            одновременно придет несколько событий установки. или при миграции задублируются строки
            Поэтому я добавлю еще условие в котором если больше чем один аккаунт будем удалять но еще + записывать в лог
        */
        $accounts = $this->bitrix24AccountRepository->findActiveByMemberId($command->memberId);
        if (!empty($accounts)) {
            $accountIds = array_map(fn($acc) => $acc->getId(), $accounts);
            // 2. Получаем все активные установки для этих аккаунтов
            $activeInstallations = $this->applicationInstallationRepository->findActiveByAccountIds($accountIds);

            // 3. Деактивируем все активные установки и связанные аккаунты
            foreach ($activeInstallations as $installation) {
                $installation->applicationUninstalled();
                $this->applicationInstallationRepository->save($installation);
            }

            foreach ($accounts as $account) {
                //Нужна правка в контракте ушли от обязательного параметра токена!!!
                $account->applicationUninstalled();
                $this->bitrix24AccountRepository->save($account);
            }

            // Здесь сразу флашим так как это условие не всегда работает , и лучше сначало разобраться с аккаунтами и установщиками
            // которые нужно деактивировать , а после уже работаем с новыми сущностями.
            $this->flusher->flush(...$activeInstallations, ...$accounts);
        }

        $bitrix24AccountId = Uuid::v7();
        $applicationInstallationId = Uuid::v7();

        $bitrix24Account = new Bitrix24Account(
            $bitrix24AccountId,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domain->value,
            $command->authToken,
            $command->applicationVersion,
            $command->applicationScope,
            true
        );


        $bitrix24Account->applicationInstalled();

        $applicationInstallation = new ApplicationInstallation(
            $applicationInstallationId,
            $bitrix24AccountId,
            $command->applicationStatus,
            $command->portalLicenseFamily,
            $command->portalUsersCount,
            $command->contactPersonId,
            $command->bitrix24PartnerContactPersonId,
            $command->bitrix24PartnerId,
            $command->externalId,
            $command->comment,
            true
        );

        $applicationInstallation->applicationInstalled();

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation,$bitrix24Account);

        $this->logger->info(
            'ApplicationInstallations.Install.Finish',
            [
                'applicationId' => $applicationInstallationId,
                'bitrix24AccountId' => $bitrix24AccountId
            ]
        );
    }
}
