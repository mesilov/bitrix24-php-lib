<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\Services\Flusher;
use Psr\Log\LoggerInterface;

/**
 * Handler for OnApplicationDelete command.
 *
 * Soft-deletes all settings when application is uninstalled.
 * Settings are marked as deleted rather than removed from database
 * to maintain history and enable recovery if needed.
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingsItemRepository $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.OnApplicationDelete.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
        ]);

        // Get all active settings for this installation
        $settings = $this->applicationSettingRepository->findAllForInstallation($command->applicationInstallationId);

        // Mark each setting as deleted
        foreach ($settings as $setting) {
            $setting->markAsDeleted();
        }

        $this->flusher->flush();

        $this->logger->info('ApplicationSettings.OnApplicationDelete.finish', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'deletedCount' => count($settings),
        ]);
    }
}
