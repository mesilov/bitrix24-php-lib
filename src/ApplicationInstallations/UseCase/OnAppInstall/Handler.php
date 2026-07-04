<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws ApplicationInstallationNotFoundException|InvalidArgumentException|MultipleBitrix24AccountsFoundException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallation.OnAppInstall.start', [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
            'application_status' => $command->applicationStatus,
        ]);

        $applicationInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

        if (null === $applicationInstallation) {
            throw $this->buildInstallationNotFoundException($command->memberId);
        }

        assert($applicationInstallation instanceof AggregateRootEventsEmitterInterface);

        if (ApplicationInstallationStatus::new === $applicationInstallation->getStatus()) {
            $this->finishPendingInstallation($command, $applicationInstallation);

            $this->logger->info('ApplicationInstallation.OnAppInstall.finish');

            return;
        }

        if (ApplicationInstallationStatus::active === $applicationInstallation->getStatus()) {
            $this->handleRepeatedEvent($command, $applicationInstallation);

            return;
        }

        throw $this->buildInstallationNotFoundException($command->memberId);
    }

    /**
     * @throws Bitrix24AccountNotFoundException|InvalidArgumentException|MultipleBitrix24AccountsFoundException
     */
    private function finishPendingInstallation(
        Command $command,
        AggregateRootEventsEmitterInterface&ApplicationInstallationInterface $applicationInstallation
    ): void {
        $bitrix24Account = $this->findMasterAccountByMemberId($command->memberId, Bitrix24AccountStatus::new);

        $applicationInstallation->changeApplicationStatus($command->applicationStatus);
        $bitrix24Account->applicationInstalled($command->applicationToken);
        $applicationInstallation->applicationInstalled($command->applicationToken);

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->bitrix24AccountRepository->save($bitrix24Account);

        $this->flusher->flush($applicationInstallation, $bitrix24Account);
    }

    /**
     * @throws Bitrix24AccountNotFoundException|MultipleBitrix24AccountsFoundException
     */
    private function handleRepeatedEvent(
        Command $command,
        AggregateRootEventsEmitterInterface&ApplicationInstallationInterface $applicationInstallation
    ): void {
        $bitrix24Account = $this->findMasterAccountByMemberId($command->memberId, Bitrix24AccountStatus::active);

        $sameToken = $applicationInstallation->isApplicationTokenValid($command->applicationToken)
            && $bitrix24Account->isApplicationTokenValid($command->applicationToken);

        $this->logger->warning('ApplicationInstallation.OnAppInstall.duplicate', [
            'memberId' => $command->memberId,
            'domain' => $command->domainUrl->value,
            'applicationToken' => $command->applicationToken,
            'tokenMatch' => $sameToken,
        ]);
    }

    /**
     * @throws Bitrix24AccountNotFoundException
     * @throws MultipleBitrix24AccountsFoundException
     */
    private function findMasterAccountByMemberId(
        string $memberId,
        Bitrix24AccountStatus $bitrix24AccountStatus
    ): AggregateRootEventsEmitterInterface&Bitrix24AccountInterface {
        $bitrix24Accounts = $this->bitrix24AccountRepository->findByMemberId(
            $memberId,
            $bitrix24AccountStatus
        );

        // Filter for master accounts only
        $masterAccounts = array_filter(
            $bitrix24Accounts,
            fn (Bitrix24AccountInterface $bitrix24Account): bool => $bitrix24Account->isMasterAccount()
        );

        if ([] === $masterAccounts) {
            throw new Bitrix24AccountNotFoundException(
                sprintf('Bitrix24 account with status %s not found for member ID %s', $bitrix24AccountStatus->value, $memberId)
            );
        }

        if (1 !== count($masterAccounts)) {
            throw new MultipleBitrix24AccountsFoundException(
                sprintf('Multiple Bitrix24 accounts with status %s found for member ID %s', $bitrix24AccountStatus->value, $memberId)
            );
        }

        $masterAccount = reset($masterAccounts);
        assert($masterAccount instanceof AggregateRootEventsEmitterInterface);

        return $masterAccount;
    }

    private function buildInstallationNotFoundException(string $memberId): ApplicationInstallationNotFoundException
    {
        return new ApplicationInstallationNotFoundException(
            sprintf('Pending application installation not found for member ID %s', $memberId)
        );
    }
}
