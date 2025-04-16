<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Carbon\CarbonImmutable;
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
        $this->logger->info('ApplicationInstallations.InstallStart.start', [
            'applicationStatus' => $command->applicationStatus,
            'portalLicenseFamily' => $command->portalLicenseFamily,
            'portalUsersCount' => $command->portalUsersCount,
            'contactPersonId' => $command->contactPersonId,
            'bitrix24PartnerContactPersonId' => $command->bitrix24PartnerContactPersonId,
            'bitrix24PartnerId' => $command->bitrix24PartnerId,
            'externalId' => $command->externalId,
            'comment' => $command->comment,
            'bitrix24UserId' => $command->bitrix24UserId,
            'isBitrix24UserAdmin' => $command->isBitrix24UserAdmin,
            'memberId' => $command->memberId,
            'domain' => $command->domain,
            'authToken' => $command->authToken,
            'applicationVersion' => $command->applicationVersion,
            'applicationScope' => $command->applicationScope
        ]);

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

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account->applicationInstalled($applicationToken);
        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $applicationInstallation = new ApplicationInstallation(
            $applicationInstallationId,
            ApplicationInstallationStatus::new,
            new CarbonImmutable(),
            new CarbonImmutable(),
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

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation);

        $applicationInstallation->applicationInstalled();

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation);

        $this->logger->info(
            'ApplicationInstallations.InstallStart.Finish',
            [
                'id' => $command->uuid->toRfc4122(),
            ]
        );
    }
}
