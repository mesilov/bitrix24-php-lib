<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Delete;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Core\Exceptions\ItemNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Handler for Delete command.
 *
 * Deletes global application settings only.
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingsItemRepositoryInterface $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws ItemNotFoundException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.Delete.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
        ]);

        // Find global setting by key
        $allSettings = $this->applicationSettingRepository->findAllForInstallation(
            $command->applicationInstallationId
        );

        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === $command->key && $allSetting->isGlobal()) {
                $setting = $allSetting;

                break;
            }
        }

        if (!$setting instanceof ApplicationSettingsItemInterface) {
            throw new ItemNotFoundException(sprintf('Setting with key "%s" not found.', $command->key));
        }

        $settingId = $setting->getId()->toRfc4122();

        // Soft-delete: mark as deleted instead of removing
        $setting->markAsDeleted();
        $this->flusher->flush();

        $this->logger->info('ApplicationSettings.Delete.finish', [
            'settingId' => $settingId,
            'softDeleted' => true,
        ]);
    }
}
