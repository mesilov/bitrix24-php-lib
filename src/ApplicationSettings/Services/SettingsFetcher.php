<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\ItemNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service for fetching settings with cascading resolution.
 *
 * Priority order:
 * 1. Personal setting (if userId provided)
 * 2. Departmental setting (if departmentId provided)
 * 3. Global setting (fallback)
 */
readonly class SettingsFetcher
{
    public function __construct(
        private ApplicationSettingsItemRepositoryInterface $repository,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {}

    /**
     * Get setting item with cascading resolution.
     *
     * Tries to find setting in following order:
     * 1. Personal (if userId provided)
     * 2. Departmental (if departmentId provided)
     * 3. Global (always as fallback)
     *
     * @throws ItemNotFoundException
     */
    public function getItem(
        Uuid $uuid,
        string $key,
        ?int $userId = null,
        ?int $departmentId = null
    ): ApplicationSettingsItemInterface {
        $this->logger->debug('SettingsFetcher.getItem.start', [
            'uuid' => $uuid->toRfc4122(),
            'key' => $key,
            'userId' => $userId,
            'departmentId' => $departmentId,
        ]);

        $allSettings = $this->repository->findAllForInstallationByKey($uuid, $key);

        // Try to find personal setting (highest priority)
        if (null !== $userId) {
            foreach ($allSettings as $allSetting) {
                if ($allSetting->isPersonal()
                    && $allSetting->getB24UserId() === $userId
                ) {
                    $this->logger->debug('SettingsFetcher.getItem.found', [
                        'scope' => 'personal',
                        'settingId' => $allSetting->getId()->toRfc4122(),
                    ]);

                    return $allSetting;
                }
            }
        }

        // Try to find departmental setting (medium priority)
        if (null !== $departmentId) {
            foreach ($allSettings as $allSetting) {
                if ($allSetting->isDepartmental()
                    && $allSetting->getB24DepartmentId() === $departmentId
                ) {
                    $this->logger->debug('SettingsFetcher.getItem.found', [
                        'scope' => 'departmental',
                        'settingId' => $allSetting->getId()->toRfc4122(),
                    ]);

                    return $allSetting;
                }
            }
        }

        // Fallback to global setting (lowest priority)
        foreach ($allSettings as $allSetting) {
            if ($allSetting->isGlobal()) {
                $this->logger->debug('SettingsFetcher.getItem.found', [
                    'scope' => 'global',
                    'settingId' => $allSetting->getId()->toRfc4122(),
                ]);

                return $allSetting;
            }
        }

        $this->logger->warning('SettingsFetcher.getItem.notFound', [
            'uuid' => $uuid->toRfc4122(),
            'key' => $key,
        ]);

        throw new ItemNotFoundException(sprintf('Settings item with key "%s" not found', $key));
    }

    /**
     * Get setting value with optional deserialization to object.
     *
     * If $class is provided, deserializes JSON value into specified class using Symfony Serializer.
     * If $class is null, returns raw string value.
     *
     * @template T of object
     *
     * @param null|class-string<T> $class Optional class to deserialize into
     *
     * @return ($class is null ? string : T)
     *
     * @throws ItemNotFoundException if setting not found at any level
     */
    public function getValue(
        Uuid $uuid,
        string $key,
        ?int $userId = null,
        ?int $departmentId = null,
        ?string $class = null
    ): object|string {
        $this->logger->debug('SettingsFetcher.getValue.start', [
            'uuid' => $uuid->toRfc4122(),
            'key' => $key,
            'class' => $class,
        ]);

        $applicationSettingsItem = $this->getItem($uuid, $key, $userId, $departmentId);
        $value = $applicationSettingsItem->getValue();

        // If no class specified, return raw string
        if (null === $class) {
            $this->logger->debug('SettingsFetcher.getValue.returnRaw', [
                'key' => $key,
                'valueLength' => strlen($value),
            ]);

            return $value;
        }

        // Deserialize to object
        try {
            $object = $this->serializer->deserialize($value, $class, 'json');

            $this->logger->debug('SettingsFetcher.getValue.deserialized', [
                'key' => $key,
                'class' => $class,
            ]);

            return $object;
        } catch (\Throwable $throwable) {
            $this->logger->error('SettingsFetcher.getValue.deserializationFailed', [
                'key' => $key,
                'class' => $class,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
