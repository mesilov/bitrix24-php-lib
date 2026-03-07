<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Contract\ApplicationSettings\Infrastructure;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Contract test for ApplicationSettingsItemRepositoryInterface implementations.
 *
 * This abstract test class ensures that all repository implementations
 * (Doctrine, InMemory, etc.) behave consistently according to the interface contract.
 *
 * To test a specific implementation, extend this class and implement createRepository().
 */
abstract class ApplicationSettingsItemRepositoryInterfaceContractTest extends TestCase
{
    protected ApplicationSettingsItemRepositoryInterface $repository;

    /**
     * Create repository instance to test.
     *
     * Implementations should return a fresh repository instance for each test.
     */
    abstract protected function createRepository(): ApplicationSettingsItemRepositoryInterface;

    /**
     * Clear repository state between tests (optional).
     *
     * Override this method if the repository implementation supports clearing.
     */
    protected function clearRepository(): void
    {
        // Override in implementation if needed
    }

    /**
     * Flush changes to persistence layer (optional).
     *
     * Override this method for repositories that require explicit flush (e.g., Doctrine).
     */
    protected function flushChanges(): void
    {
        // Override in implementation if needed (e.g., EntityManager::flush())
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createRepository();
        $this->clearRepository();
    }

    /**
     * Test that save() stores a setting and it can be retrieved by ID.
     */
    public function testSaveStoresSettingAndCanBeRetrievedById(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationSettingsItem = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'test.key',
            value: 'test value',
            isRequired: true
        );

        $this->repository->save($applicationSettingsItem);
        $this->flushChanges();

        $retrieved = $this->repository->findById($applicationSettingsItem->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals($applicationSettingsItem->getId()->toRfc4122(), $retrieved->getId()->toRfc4122());
        $this->assertEquals('test.key', $retrieved->getKey());
        $this->assertEquals('test value', $retrieved->getValue());
        $this->assertTrue($retrieved->isRequired());
    }

    /**
     * Test that findById() returns null for non-existent ID.
     */
    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        $uuidV7 = Uuid::v7();

        $result = $this->repository->findById($uuidV7);

        $this->assertNull($result);
    }

    /**
     * Test that findById() does not return soft-deleted settings.
     */
    public function testFindByIdDoesNotReturnDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationSettingsItem = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'test.key',
            value: 'test value',
            isRequired: false
        );

        $this->repository->save($applicationSettingsItem);
        $this->flushChanges();
        $applicationSettingsItem->markAsDeleted();
        $this->repository->save($applicationSettingsItem);
        $this->flushChanges();

        $result = $this->repository->findById($applicationSettingsItem->getId());

        $this->assertNull($result);
    }

    /**
     * Test that findAllForInstallation() returns all active settings for an installation.
     */
    public function testFindAllForInstallationReturnsAllActiveSettings(): void
    {
        $uuidV7 = Uuid::v7();
        $otherInstallationId = Uuid::v7();

        $setting1 = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'key.one',
            value: 'value1',
            isRequired: true
        );

        $setting2 = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'key.two',
            value: 'value2',
            isRequired: false
        );

        $otherSetting = new ApplicationSettingsItem(
            applicationInstallationId: $otherInstallationId,
            key: 'other.key',
            value: 'other value',
            isRequired: false
        );

        $this->repository->save($setting1);
        $this->flushChanges();
        $this->repository->save($setting2);
        $this->flushChanges();
        $this->repository->save($otherSetting);
        $this->flushChanges();

        $results = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(ApplicationSettingsItemInterface::class, $results);
    }

    /**
     * Test that findAllForInstallation() excludes soft-deleted settings.
     */
    public function testFindAllForInstallationExcludesDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'active.key',
            value: 'active value',
            isRequired: true
        );

        $deletedSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'deleted.key',
            value: 'deleted value',
            isRequired: false
        );

        $this->repository->save($activeSetting);
        $this->flushChanges();
        $this->repository->save($deletedSetting);
        $this->flushChanges();

        $deletedSetting->markAsDeleted();
        $this->repository->save($deletedSetting);
        $this->flushChanges();

        $results = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(1, $results);
        $this->assertEquals('active.key', $results[0]->getKey());
    }

    /**
     * Test that findAllForInstallationByKey() returns settings filtered by key.
     */
    public function testFindAllForInstallationByKeyReturnsSettingsFilteredByKey(): void
    {
        $uuidV7 = Uuid::v7();

        // Global setting
        $globalSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'theme',
            value: 'light',
            isRequired: false
        );

        // Personal setting for user 123
        $personalSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'theme',
            value: 'dark',
            isRequired: false,
            b24UserId: 123
        );

        // Different key - should not be returned
        $differentKeySetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'language',
            value: 'en',
            isRequired: true
        );

        $this->repository->save($globalSetting);
        $this->flushChanges();
        $this->repository->save($personalSetting);
        $this->flushChanges();
        $this->repository->save($differentKeySetting);
        $this->flushChanges();

        $results = $this->repository->findAllForInstallationByKey($uuidV7, 'theme');

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals('theme', $result->getKey());
        }
    }

    /**
     * Test that findAllForInstallationByKey() excludes soft-deleted settings.
     */
    public function testFindAllForInstallationByKeyExcludesDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'config',
            value: 'active',
            isRequired: false
        );

        $deletedSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'config',
            value: 'deleted',
            isRequired: false,
            b24UserId: 456
        );

        $this->repository->save($activeSetting);
        $this->flushChanges();
        $this->repository->save($deletedSetting);
        $this->flushChanges();

        $deletedSetting->markAsDeleted();
        $this->repository->save($deletedSetting);
        $this->flushChanges();

        $results = $this->repository->findAllForInstallationByKey($uuidV7, 'config');

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results[0]->getValue());
    }

    /**
     * Test that findAllForInstallationByKey() returns empty array for non-existent key.
     */
    public function testFindAllForInstallationByKeyReturnsEmptyArrayForNonExistentKey(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'existing.key',
            value: 'value',
            isRequired: false
        );

        $this->repository->save($applicationSettingsItem);
        $this->flushChanges();

        $results = $this->repository->findAllForInstallationByKey($uuidV7, 'non.existent.key');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test that save() updates an existing setting when called twice.
     */
    public function testSaveUpdatesExistingSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationSettingsItem = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'updateable.key',
            value: 'initial value',
            isRequired: false
        );

        $this->repository->save($applicationSettingsItem);
        $this->flushChanges();

        $applicationSettingsItem->updateValue('updated value', 100);
        $this->repository->save($applicationSettingsItem);
        $this->flushChanges();

        $retrieved = $this->repository->findById($applicationSettingsItem->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals('updated value', $retrieved->getValue());
    }

    /**
     * Test that repository handles different scopes correctly.
     */
    public function testRepositoryHandlesDifferentScopes(): void
    {
        $uuidV7 = Uuid::v7();

        // Global
        $globalSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'multi.scope',
            value: 'global',
            isRequired: false
        );

        // Personal
        $personalSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'multi.scope',
            value: 'personal',
            isRequired: false,
            b24UserId: 123
        );

        // Departmental
        $departmentalSetting = new ApplicationSettingsItem(
            applicationInstallationId: $uuidV7,
            key: 'multi.scope',
            value: 'departmental',
            isRequired: false,
            b24DepartmentId: 456
        );

        $this->repository->save($globalSetting);
        $this->flushChanges();
        $this->repository->save($personalSetting);
        $this->flushChanges();
        $this->repository->save($departmentalSetting);
        $this->flushChanges();

        $results = $this->repository->findAllForInstallationByKey($uuidV7, 'multi.scope');

        $this->assertCount(3, $results);

        // Verify each scope is present
        $values = array_map(fn($s): string => $s->getValue(), $results);
        $this->assertContains('global', $values);
        $this->assertContains('personal', $values);
        $this->assertContains('departmental', $values);
    }
}
