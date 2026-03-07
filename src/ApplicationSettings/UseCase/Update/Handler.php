<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Update;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Handler for Update command.
 *
 * Updates existing setting only. Throws exception if setting does not exist.
 * Emits ApplicationSettingsItemChangedEvent automatically via entity.
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingsItemRepositoryInterface $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.Update.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
            'b24UserId' => $command->b24UserId,
            'b24DepartmentId' => $command->b24DepartmentId,
        ]);

        // Find existing setting with the same scope
        $allSettings = $this->applicationSettingRepository->findAllForInstallation(
            $command->applicationInstallationId
        );

        $setting = $this->findMatchingSetting(
            $allSettings,
            $command->key,
            $command->b24UserId,
            $command->b24DepartmentId
        );

        if (!$setting instanceof ApplicationSettingsItemInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Setting with key "%s" does not exist for this scope. Use Create command to add it.',
                    $command->key
                )
            );
        }

        // Update existing setting (this will emit ApplicationSettingsItemChangedEvent)
        $setting->updateValue($command->value, $command->changedByBitrix24UserId);

        $this->logger->debug('ApplicationSettings.Update.updated', [
            'settingId' => $setting->getId()->toRfc4122(),
            'changedBy' => $command->changedByBitrix24UserId,
        ]);

        /** @var AggregateRootEventsEmitterInterface&ApplicationSettingsItemInterface $setting */
        $this->flusher->flush($setting);

        $this->logger->info('ApplicationSettings.Update.finish', [
            'settingId' => $setting->getId()->toRfc4122(),
        ]);
    }

    /**
     * Find setting that matches key and scope.
     *
     * @param ApplicationSettingsItemInterface[] $settings
     */
    private function findMatchingSetting(
        array $settings,
        string $key,
        ?int $b24UserId,
        ?int $b24DepartmentId
    ): ?ApplicationSettingsItemInterface {
        foreach ($settings as $setting) {
            if ($setting->getKey() === $key
                && $setting->getB24UserId() === $b24UserId
                && $setting->getB24DepartmentId() === $b24DepartmentId
            ) {
                return $setting;
            }
        }

        return null;
    }
}
