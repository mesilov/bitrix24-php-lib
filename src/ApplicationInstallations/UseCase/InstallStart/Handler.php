<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\InstallStart;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.InstallStart.start', [
            'id' => $command->uuid->toRfc4122(),
        ]);

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
            $command->comment
        );

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush();

        $this->logger->info(
            'Bitrix24Accounts.InstallStart.Finish',
            [
                'id' => $command->uuid->toRfc4122(),
            ]
        );
    }
}
