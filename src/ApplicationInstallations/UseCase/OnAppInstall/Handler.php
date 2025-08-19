<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallation.OnAppInstall.start', [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
            'application_status' => $command->applicationStatus,
        ]);

        /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
        $applicationInstallation = $this->applicationInstallationRepository->findActiveInstallationWithAccountByMemberId($command->memberId);

        $applicationStatus = new ApplicationStatus($command->applicationStatus);

        $applicationInstallation->changeApplicationStatus($applicationStatus);

        $applicationInstallation->setApplicationToken($command->applicationToken);

        $this->applicationInstallationRepository->save($applicationInstallation);

        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface $bitrix24Account */
        $bitrix24Account = $this->bitrix24AccountRepository->findMasterByMemberId(
            $command->memberId,
            Bitrix24AccountStatus::active,
        );

        $bitrix24Account->setApplicationToken($command->applicationToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);

        $this->flusher->flush($applicationInstallation, $bitrix24Account);

        $this->logger->info('ApplicationInstallation.OnAppInstall.finish');
    }
}
