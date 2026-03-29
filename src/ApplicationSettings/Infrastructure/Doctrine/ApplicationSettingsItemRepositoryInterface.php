<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Interface for ApplicationSetting repository.
 *
 * @todo Move this interface to b24-php-sdk contracts after stabilization
 */
interface ApplicationSettingsItemRepositoryInterface
{
    /**
     * Save application setting.
     */
    public function save(ApplicationSettingsItemInterface $applicationSettingsItem): void;

    /**
     * Delete application setting.
     */
    public function delete(ApplicationSettingsItemInterface $applicationSettingsItem): void;

    /**
     * Find setting by ID.
     */
    public function findById(Uuid $uuid): ?ApplicationSettingsItemInterface;

    /**
     * Find all settings for application installation (all scopes).
     *
     * @return ApplicationSettingsItemInterface[]
     */
    public function findAllForInstallation(Uuid $uuid): array;

    /**
     * Find all settings for application installation by key (all scopes with same key).
     *
     * @return ApplicationSettingsItemInterface[]
     */
    public function findAllForInstallationByKey(Uuid $uuid, string $key): array;
}
