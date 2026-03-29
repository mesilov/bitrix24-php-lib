<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\UseCase\Update;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\ApplicationSettings\UseCase\Update\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Update\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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

    public function testCanUpdateExistingSetting(): void
    {
        $uuidV7 = Uuid::v7();

        // Create initial setting
        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'update.test',
            'initial_value',
            false,
            null,
            null,
            null
        );
        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Update the setting
        $updateCommand = new Command(
            $uuidV7,
            'update.test',
            'updated_value',
            null,
            null,
            123
        );
        $this->handler->handle($updateCommand);
        EntityManagerFactory::get()->clear();

        // Verify update
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $updatedSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'update.test' && $allSetting->isGlobal()) {
                $updatedSetting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($updatedSetting);
        $this->assertEquals('updated_value', $updatedSetting->getValue());
    }

    public function testThrowsExceptionWhenUpdatingNonExistentSetting(): void
    {
        $uuidV7 = Uuid::v7();

        $updateCommand = new Command(
            $uuidV7,
            'non.existent',
            'some_value'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting with key "non.existent" does not exist for this scope');

        $this->handler->handle($updateCommand);
    }

    public function testCanUpdatePersonalSetting(): void
    {
        $uuidV7 = Uuid::v7();

        // Create initial personal setting
        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'personal.test',
            'user_value',
            false,
            123,
            null,
            null
        );
        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Update personal setting
        $updateCommand = new Command(
            applicationInstallationId: $uuidV7,
            key: 'personal.test',
            value: 'new_user_value',
            b24UserId: 123,
            b24DepartmentId: null,
            changedByBitrix24UserId: 456
        );
        $this->handler->handle($updateCommand);
        EntityManagerFactory::get()->clear();

        // Verify update
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $updatedSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'personal.test' && $allSetting->isPersonal() && $allSetting->getB24UserId() === 123) {
                $updatedSetting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($updatedSetting);
        $this->assertEquals('new_user_value', $updatedSetting->getValue());
    }

    public function testCanUpdateDepartmentalSetting(): void
    {
        $uuidV7 = Uuid::v7();

        // Create initial departmental setting
        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'dept.test',
            'dept_value',
            false,
            null,
            456,
            null
        );
        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Update departmental setting
        $updateCommand = new Command(
            applicationInstallationId: $uuidV7,
            key: 'dept.test',
            value: 'new_dept_value',
            b24UserId: null,
            b24DepartmentId: 456,
            changedByBitrix24UserId: 789
        );
        $this->handler->handle($updateCommand);
        EntityManagerFactory::get()->clear();

        // Verify update
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $updatedSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'dept.test' && $allSetting->isDepartmental() && $allSetting->getB24DepartmentId() === 456) {
                $updatedSetting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($updatedSetting);
        $this->assertEquals('new_dept_value', $updatedSetting->getValue());
    }
}
