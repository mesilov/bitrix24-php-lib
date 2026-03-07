<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Handler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service for creating default application settings during installation.
 *
 * This service is responsible for initializing default global settings
 * when an application is installed on a Bitrix24 portal
 */
readonly class DefaultSettingsInstaller
{
    public function __construct(
        private Handler $createHandler,
        private LoggerInterface $logger
    ) {}

    /**
     * Create default settings for application installation.
     *
     * @param Uuid                                                $uuid            Application installation UUID
     * @param array<string, array{value: string, required: bool}> $defaultSettings Settings with value and required flag
     */
    public function createDefaultSettings(
        Uuid $uuid,
        array $defaultSettings
    ): void {
        $this->logger->info('DefaultSettingsInstaller.createDefaultSettings.start', [
            'applicationInstallationId' => $uuid->toRfc4122(),
            'settingsCount' => count($defaultSettings),
        ]);

        foreach ($defaultSettings as $key => $config) {
            // Use Create UseCase to create new setting
            $command = new Command(
                applicationInstallationId: $uuid,
                key: $key,
                value: $config['value'],
                isRequired: $config['required']
            );

            $this->createHandler->handle($command);

            $this->logger->debug('DefaultSettingsInstaller.settingProcessed', [
                'key' => $key,
                'isRequired' => $config['required'],
            ]);
        }

        $this->logger->info('DefaultSettingsInstaller.createDefaultSettings.finish', [
            'applicationInstallationId' => $uuid->toRfc4122(),
        ]);
    }
}
