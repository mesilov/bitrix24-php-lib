<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\Events\ApplicationUninstallationCompletedEvent;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\ApplicationDataCleanerInterface;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationStoreInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class Handler
{
    public function __construct(
        private UninstallationStoreInterface $store,
        private ApplicationDataCleanerInterface $cleaner,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(#[\SensitiveParameter] Command $command): void
    {
        // Never log the request, its application token, or upstream exception text.
        $context = $this->store->begin($command);
        if (null === $context) {
            $this->logger->info('ApplicationInstallations.Uninstall.ignored');

            return;
        }

        if (!$this->store->complete($context, $this->cleaner)) {
            return;
        }

        $this->logger->info('ApplicationInstallations.Uninstall.completed', [
            'installationId' => $context->installationId->toRfc4122(),
            'clean' => $context->clean,
        ]);

        try {
            $this->eventDispatcher->dispatch(new ApplicationUninstallationCompletedEvent($context->installationId, $context->clean));
        } catch (\Throwable) {
            // Completion is already durable. Observers must not turn it into a failed cleanup.
            $this->logger->warning('ApplicationInstallations.Uninstall.notification_failed');
        }
    }
}
