<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\InstallStart;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Carbon\CarbonImmutable;
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
        $this->logger->info('ApplicationInstallations.InstallStart.start', [
            'id' => $command->uuid->toRfc4122(),
        ]);

        $bitrix24Account = new Bitrix24Account(
            $command->bitrix24AccountUuid,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domain->value,
            Bitrix24AccountStatus::new,
            $command->authToken,
            new CarbonImmutable(),
            new CarbonImmutable(),
            $command->applicationVersion,
            $command->applicationScope,
            true
        );

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $applicationInstallation = new ApplicationInstallation(
            $command->uuid,
            ApplicationInstallationStatus::new,
            new CarbonImmutable(),
            new CarbonImmutable(),
            $command->bitrix24AccountId,
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

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation);

        $this->logger->info(
            'Bitrix24Accounts.InstallStart.Finish',
            [
                'id' => $command->uuid->toRfc4122(),
            ]
        );
    }
}
