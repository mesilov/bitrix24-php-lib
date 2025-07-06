<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepository $bitrix24AccountRepository,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws LogicException
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.Install.start', [
            (string) $command,
        ]);

        /*
         * Аккаунтов может быть несколько , но установку на портал проводят только 1 раз , то есть есть мастер аккаунт который нужно получать.
         * У остальных аккаунтов установок быть не может это просто (доступы/авторизации).
         * Решить какое поле для мастера добавить в аккаунт.
         */
        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface[] $b24Accounts */
        $b24Accounts = $this->bitrix24AccountRepository->findActiveByMemberId($command->memberId);

        if ([] !== $b24Accounts) {
            $entitiesToFlush = [];
            foreach ($b24Accounts as $b24Account) {
                $isMaster = $b24Account->isMasterAccount();
                if ($isMaster) {
                    /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
                    $activeInstallation = $this->applicationInstallationRepository->findActiveByAccountId($b24Account->getId());
                    $activeInstallation->applicationUninstalled();
                    $this->applicationInstallationRepository->save($activeInstallation);
                    $entitiesToFlush[] = $activeInstallation;
                }

                $b24Account->applicationUninstalled(null);
                $this->bitrix24AccountRepository->save($b24Account);
                $entitiesToFlush[] = $b24Account;
            }

            /* Здесь сразу флашим так как это условие не всегда работает , и лучше сначало разобраться с аккаунтами и установщиками
             которые нужно деактивировать , а после уже работаем с новыми сущностями. */
            $this->flusher->flush(...$entitiesToFlush);
        }

        $uuidV7 = Uuid::v7();
        $applicationInstallationId = Uuid::v7();

        $bitrix24Account = new Bitrix24Account(
            $uuidV7,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domain->value,
            $command->authToken,
            $command->applicationVersion,
            $command->applicationScope,
            true,
            true
        );

        $bitrix24Account->applicationInstalled(null);

        $applicationInstallation = new ApplicationInstallation(
            $applicationInstallationId,
            $uuidV7,
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
        $this->flusher->flush($applicationInstallation, $bitrix24Account);

        $this->logger->info(
            'ApplicationInstallations.Install.Finish',
            [
                'applicationId' => $applicationInstallationId,
                'bitrix24AccountId' => $uuidV7,
            ]
        );
    }
}
