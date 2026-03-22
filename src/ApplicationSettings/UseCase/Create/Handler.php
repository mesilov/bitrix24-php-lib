<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Create;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Handler for Create command.
 *
 * Creates new setting only. Throws exception if setting already exists.
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingsItemRepositoryInterface $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.Create.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
            'b24UserId' => $command->b24UserId,
            'b24DepartmentId' => $command->b24DepartmentId,
        ]);

        // Check if setting already exists with the same scope
        $allSettings = $this->applicationSettingRepository->findAllForInstallation(
            $command->applicationInstallationId
        );

        $existingSetting = $this->findMatchingSetting(
            $allSettings,
            $command->key,
            $command->b24UserId,
            $command->b24DepartmentId
        );

        if ($existingSetting instanceof ApplicationSettingsItemInterface) {
            throw new InvalidArgumentException(sprintf('Setting with key "%s" already exists.', $command->key));
        }

        // Create new setting
        $applicationSettingsItem = new ApplicationSettingsItem(
            $command->applicationInstallationId,
            $command->key,
            $command->value,
            $command->isRequired,
            $command->b24UserId,
            $command->b24DepartmentId,
            $command->changedByBitrix24UserId
        );
        $this->applicationSettingRepository->save($applicationSettingsItem);

        $this->logger->debug('ApplicationSettings.Create.created', [
            'settingId' => $applicationSettingsItem->getId()->toRfc4122(),
            'isRequired' => $command->isRequired,
            'changedBy' => $command->changedByBitrix24UserId,
        ]);

        /** @var AggregateRootEventsEmitterInterface&ApplicationSettingsItemInterface $applicationSettingsItem */
        $this->flusher->flush($applicationSettingsItem);

        $this->logger->info('ApplicationSettings.Create.finish', [
            'settingId' => $applicationSettingsItem->getId()->toRfc4122(),
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
