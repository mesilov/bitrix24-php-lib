<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
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

        /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
        // todo fix https://github.com/mesilov/bitrix24-php-lib/issues/59
        $applicationInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

        if (null === $applicationInstallation) {
            throw new ApplicationInstallationNotFoundException(
                sprintf('Application installation not found for member ID %s', $command->memberId)
            );
        }

        $applicationInstallation->changeApplicationStatus($command->applicationStatus);

        $applicationInstallation->setApplicationToken($command->applicationToken);

        $this->applicationInstallationRepository->save($applicationInstallation);

        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface $bitrix24Account */
        $bitrix24Account = $this->findMasterAccountByMemberId($command->memberId);

        $bitrix24Account->setApplicationToken($command->applicationToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);

        $this->flusher->flush($applicationInstallation, $bitrix24Account);

        $this->logger->info('ApplicationInstallation.OnAppInstall.finish');
    }

    private function findMasterAccountByMemberId(string $memberId): Bitrix24AccountInterface
    {
        $bitrix24Accounts = $this->bitrix24AccountRepository->findByMemberId(
            $memberId,
            Bitrix24AccountStatus::active,
            null,
            null
        );

        // Filter for master accounts only
        $masterAccounts = array_filter(
            $bitrix24Accounts,
            fn (Bitrix24AccountInterface $bitrix24Account): bool => $bitrix24Account->isMasterAccount()
        );

        if ([] === $masterAccounts) {
            throw new Bitrix24AccountNotFoundException('Bitrix24 account not found for member ID '.$memberId);
        }

        if (1 !== count($masterAccounts)) {
            throw new MultipleBitrix24AccountsFoundException('Multiple Bitrix24 accounts found for member ID '.$memberId);
        }

        return reset($masterAccounts);
    }
}
