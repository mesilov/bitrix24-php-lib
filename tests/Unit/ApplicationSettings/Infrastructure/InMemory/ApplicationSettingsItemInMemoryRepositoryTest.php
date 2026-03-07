<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Infrastructure\InMemory;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\Tests\Helpers\ApplicationSettings\ApplicationSettingsItemInMemoryRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Tests for InMemory-specific functionality (not covered by contract tests).
 *
 * @internal
 */
#[CoversClass(ApplicationSettingsItemInMemoryRepository::class)]
class ApplicationSettingsItemInMemoryRepositoryTest extends TestCase
{
    private ApplicationSettingsItemInMemoryRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ApplicationSettingsItemInMemoryRepository();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    /**
     * Test InMemory-specific clear() method.
     */
    public function testClearRemovesAllSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSettingsItem($uuidV7, 'key.one', 'value1', false);
        $setting2 = new ApplicationSettingsItem($uuidV7, 'key.two', 'value2', false);

        $this->repository->save($setting1);
        $this->repository->save($setting2);

        $this->assertCount(2, $this->repository->findAllForInstallation($uuidV7));

        $this->repository->clear();

        $this->assertCount(0, $this->repository->findAllForInstallation($uuidV7));
    }

    /**
     * Test InMemory-specific getAllIncludingDeleted() method.
     */
    public function testGetAllIncludingDeletedReturnsDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSettingsItem($uuidV7, 'active.key', 'value1', false);
        $deletedSetting = new ApplicationSettingsItem($uuidV7, 'deleted.key', 'value2', false);
        $deletedSetting->markAsDeleted();

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);

        $allIncludingDeleted = $this->repository->getAllIncludingDeleted();

        $this->assertCount(2, $allIncludingDeleted);

        // Regular findAll should only return active
        $activeOnly = $this->repository->findAllForInstallation($uuidV7);
        $this->assertCount(1, $activeOnly);
    }

    /**
     * Test that getAllIncludingDeleted() returns empty array when repository is empty.
     */
    public function testGetAllIncludingDeletedReturnsEmptyArrayWhenEmpty(): void
    {
        $result = $this->repository->getAllIncludingDeleted();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
