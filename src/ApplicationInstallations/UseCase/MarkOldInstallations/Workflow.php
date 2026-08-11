<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationMarkedNeedReinstallEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class Workflow
{
    public function __construct(
        private Handler $handler,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}

    public function run(MarkOldInstallationsConfig $config): MarkOldInstallationsResult
    {
        $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.start', [
            'ttlInSeconds' => $config->ttlInSeconds,
        ]);

        $command = new Command($config->ttlInSeconds);

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

        return new MarkOldInstallationsResult($processedInstallations);
    }
}
