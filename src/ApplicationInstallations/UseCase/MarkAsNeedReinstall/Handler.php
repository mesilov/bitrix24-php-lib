<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkAsNeedReinstall;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws LogicException when installation is not in status «new» (e.g. ONAPPINSTALL arrived first)
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.MarkAsNeedReinstall.start', [
            'installationId' => $command->installationId->toRfc4122(),
        ]);

        // getById does not produce an extra SQL query when the entity is already
        // managed by Doctrine's identity map (e.g. loaded by findStaleInstallations
        // in the same session). Re-loading here also re-checks the status guard
        // against a race with a concurrently arriving ONAPPINSTALL.
        $applicationInstallation = $this->applicationInstallationRepository->getById($command->installationId);
        assert($applicationInstallation instanceof AggregateRootEventsEmitterInterface);

        $applicationInstallation->markAsNeedReinstall($command->comment);

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation);

        $this->logger->info('ApplicationInstallations.MarkAsNeedReinstall.finish', [
            'installationId' => $command->installationId->toRfc4122(),
            'createdAt' => $applicationInstallation->getCreatedAt()->toAtomString(),
        ]);
    }
}
