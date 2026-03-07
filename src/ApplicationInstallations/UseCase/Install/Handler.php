<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * At the moment, several scenarios may not be processed.
     * For example,
     * 1) For some reason, we missed the event from the B24 with token, that we need to delete the application (the portal was removed, we were not available and the request was missed or somehow)
     * 2) It is necessary to manually or somehow delete the application knowing only his Member_id (maybe something else for validation).
     *
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.Install.start', [
            'externalId' => $command->externalId,
            'bitrix24UserId' => $command->bitrix24UserId,
            'isBitrix24UserAdmin' => $command->isBitrix24UserAdmin,
            'memberId' => $command->memberId,
            'applicationToken' => $command->applicationToken,
        ]);

        /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
        // todo fix https://github.com/mesilov/bitrix24-php-lib/issues/59
        $activeInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

        if (null !== $activeInstallation) {
            $entitiesToFlush = [];

            $activeInstallation->applicationUninstalled();

            $this->applicationInstallationRepository->save($activeInstallation);

            $entitiesToFlush[] = $activeInstallation;

            /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface[] $b24Accounts */
            $b24Accounts = $this->bitrix24AccountRepository->findByMemberId($command->memberId);

            if ([] !== $b24Accounts) {
                foreach ($b24Accounts as $b24Account) {
                    $b24Account->applicationUninstalled(null);
                    $this->bitrix24AccountRepository->save($b24Account);
                    $entitiesToFlush[] = $b24Account;
                }
            }

            /*
            Here flush immediately here, since this condition does not always work,
            and it was better to at first to deal with accounts and installers
            which need to be deactivated, and then we are already working with new entities.
           */
            $this->flusher->flush(...array_filter($entitiesToFlush, fn ($entity): bool => $entity instanceof AggregateRootEventsEmitterInterface));
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

        $bitrix24Account->applicationInstalled($command->applicationToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);

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

        $applicationInstallation->applicationInstalled($command->applicationToken);

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
