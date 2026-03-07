<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for application uninstallation scenario.
 *
 * Important: This scenario is designed for ideal conditions and may not account for cases when:
 * 1) Someone manually deleted or deactivated the uninstallation record in the database, then called this handler.
 * 2) The uninstall token arrives with a delay (e.g., during rapid installation and removal of the application).
 * 3) Multiple accounts may exist in the system, but uninstallation on the portal is performed only once.
 * 4) For other accounts, uninstallation is impossible — they are used only for authorization and access.
 *
 * Possible limitations:
 * 1) If application installation was performed by an event that did not occur, the token may not be saved, leading to disputed situations during uninstallation.
 * 2) Use this handler only when confident in data integrity and correct event order.
 */
readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.Uninstall.start', [
            'domainUrl' => $command->domainUrl,
            'memberId' => $command->memberId,
            'applicationToken' => $command->applicationToken,
        ]);

        /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
        // todo fix https://github.com/mesilov/bitrix24-php-lib/issues/60
        $activeInstallation = $this->applicationInstallationRepository->findByApplicationToken($command->applicationToken);

        if (null !== $activeInstallation) {
            $this->logger->info(
                'ApplicationInstallations.Uninstall.Start'
            );

            $entitiesToFlush = [];

            $activeInstallation->applicationUninstalled($command->applicationToken);

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

            $this->flusher->flush(...array_filter($entitiesToFlush, fn ($entity): bool => $entity instanceof AggregateRootEventsEmitterInterface));

            $this->logger->info('ApplicationInstallations.Uninstall.completed', [
                'installationId' => $activeInstallation->getId(),
                'applicationToken' => $command->applicationToken,
                'flushedEntitiesCount' => count($entitiesToFlush),
            ]);
        } else {
            $this->logger->info('ApplicationInstallations.Uninstall.false_request', [
                'applicationToken' => $command->applicationToken,
                'memberId' => $command->memberId,
                'domainUrl' => $command->domainUrl,
                'message' => 'No active installation found for uninstall request',
            ]);
        }

        $this->logger->info(
            'ApplicationInstallations.Uninstall.Finish'
        );
    }
}
