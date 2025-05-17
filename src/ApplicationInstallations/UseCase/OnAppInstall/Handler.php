<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Symfony\Component\Uid\Uuid;
use Psr\Log\LoggerInterface;

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
        $this->logger->info('ApplicationInstallation.OnAppInstall.start', [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
            'application_status' => $command->applicationStatus,
        ]);

        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface $bitrix24Account */
        $bitrix24Account = $this->getAccountByMemberId($command->memberId);

        /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
        $applicationInstallation = $this->getApplicationInstallationByAccountId($bitrix24Account->getId());

        $applicationStatus = new ApplicationStatus($command->applicationStatus);

        $applicationInstallation->changeApplicationStatus($applicationStatus);

        $applicationInstallation->setToken($command->applicationToken);
        $bitrix24Account->setToken($command->applicationToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);

        $this->flusher->flush($applicationInstallation,$bitrix24Account);

        $this->logger->info('ApplicationInstallation.OnAppInstall.finish');
    }

    private function getAccountByMemberId(string $memberId): Bitrix24AccountInterface
    {
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $memberId,
            Bitrix24AccountStatus::active,
        );

        return $accounts[0];
    }

    private function getApplicationInstallationByAccountId(Uuid $b24AccountId): ApplicationInstallationInterface
    {
        $applicationInstallations = $this->applicationInstallationRepository->findByBitrix24AccountId($b24AccountId);

        return $applicationInstallations[0];
    }
}