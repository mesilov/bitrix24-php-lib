<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\UseCase\OnApplicationDelete;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingStatus;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;

    private ApplicationSettingsItemRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $this->repository = new ApplicationSettingsItemRepository($entityManager);
        $flusher = new Flusher($entityManager, $eventDispatcher);

        $this->handler = new Handler(
            $this->repository,
            $flusher,
            new NullLogger()
        );
    }

    public function testCanSoftDeleteAllSettingsForInstallation(): void
    {
        $uuidV7 = Uuid::v7();

        // Create multiple settings
        $setting1 = new ApplicationSettingsItem(
            $uuidV7,
            'setting.one',
            'value1',
            false
        );

        $setting2 = new ApplicationSettingsItem(
            $uuidV7,
            'setting.two',
            'value2',
            false
        );

        $setting3 = new ApplicationSettingsItem(
            $uuidV7,
            'setting.three',
            'value3',
            true // required
        );

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        $this->repository->save($setting3);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Execute soft-delete
        $command = new Command($uuidV7);
        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        // Settings should not be found by regular find methods
        $activeSettings = $this->repository->findAllForInstallation($uuidV7);
        $this->assertCount(0, $activeSettings);

        // But should still exist in database with deleted status
        $allSettings = EntityManagerFactory::get()
            ->createQueryBuilder()
            ->select('s')
            ->from(ApplicationSettingsItem::class, 's')
            ->where('s.applicationInstallationId = :appId')
            ->setParameter('appId', $uuidV7)
            ->getQuery()
            ->getResult();

        $this->assertCount(3, $allSettings);

        foreach ($allSettings as $allSetting) {
            $this->assertFalse($allSetting->isActive());
        }
    }

    public function testDoesNotAffectOtherInstallations(): void
    {
        $uuidV7 = Uuid::v7();
        $installation2 = Uuid::v7();

        // Create settings for two installations
        $setting1 = new ApplicationSettingsItem(
            $uuidV7,
            'setting',
            'value1',
            false
        );

        $setting2 = new ApplicationSettingsItem(
            $installation2,
            'setting',
            'value2',
            false
        );

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Delete only first installation settings
        $command = new Command($uuidV7);
        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        // First installation settings should be soft-deleted
        $installation1Settings = $this->repository->findAllForInstallation($uuidV7);
        $this->assertCount(0, $installation1Settings);

        // Second installation settings should remain active
        $installation2Settings = $this->repository->findAllForInstallation($installation2);
        $this->assertCount(1, $installation2Settings);
        $this->assertTrue($installation2Settings[0]->isActive());
    }

    public function testOnlyDeletesActiveSettings(): void
    {
        $uuidV7 = Uuid::v7();

        // Create active and already deleted settings
        $activeSetting = new ApplicationSettingsItem(
            $uuidV7,
            'active',
            'value',
            false
        );

        $deletedSetting = new ApplicationSettingsItem(
            $uuidV7,
            'deleted',
            'value',
            false,
            null,
            null,
            null,
            ApplicationSettingStatus::Deleted
        );

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);
        EntityManagerFactory::get()->flush();

        $initialUpdatedAt = $deletedSetting->getUpdatedAt();
        EntityManagerFactory::get()->clear();

        // Execute soft-delete
        $command = new Command($uuidV7);
        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        // Load the already deleted setting
        $reloadedDeleted = EntityManagerFactory::get()
            ->find(ApplicationSettingsItem::class, $deletedSetting->getId());

        // updatedAt should not have changed for already deleted setting
        $this->assertEquals($initialUpdatedAt->format('Y-m-d H:i:s'), $reloadedDeleted->getUpdatedAt()->format('Y-m-d H:i:s'));
    }
}
