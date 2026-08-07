<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationMarkedNeedReinstallEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class Workflow
{
    public function __construct(
        private Handler $handler,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}

    public function run(MarkOldInstallationsConfig $config): MarkOldInstallationsResult
    {
        $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.start', [
            'ttlInSeconds' => $config->ttlInSeconds,
            'memberId' => $config->memberId,
            'dryRun' => $config->dryRun,
        ]);

        if ($config->dryRun) {
            $olderThan = new CarbonImmutable();
            $olderThan = $olderThan->subSeconds($config->ttlInSeconds);

            $staleInstallations = $this->applicationInstallationRepository->findStaleInstallations(
                ApplicationInstallationStatus::new,
                $olderThan,
                $config->memberId
            );

            $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.dryRun', [
                'foundCount' => count($staleInstallations),
            ]);

            return new MarkOldInstallationsResult(true, staleInstallations: $staleInstallations);
        }

        $command = new Command($config->ttlInSeconds, $config->memberId);

        $collector = new MarkOldInstallationsCollector();
        $listener = $collector->add(...);

        $this->eventDispatcher->addListener(
            ApplicationInstallationMarkedNeedReinstallEvent::class,
            $listener
        );

        try {
            $this->handler->handle($command);
        } finally {
            $this->eventDispatcher->removeListener(
                ApplicationInstallationMarkedNeedReinstallEvent::class,
                $listener
            );
        }

        $processedInstallations = $collector->getEvents();

        $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.finish', [
            'processedCount' => count($processedInstallations),
        ]);

        return new MarkOldInstallationsResult(false, processedInstallations: $processedInstallations);
    }
}
