<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\UseCase\Create;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Handler;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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

    public function testCanCreateNewSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $command = new Command(
            $uuidV7,
            'new.setting',
            '{"test":"value"}'
        );

        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        // Find created setting
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'new.setting' && $allSetting->isGlobal()) {
                $setting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($setting);
        $this->assertEquals('new.setting', $setting->getKey());
        $this->assertEquals('{"test":"value"}', $setting->getValue());
    }

    public function testThrowsExceptionWhenCreatingDuplicateSetting(): void
    {
        $uuidV7 = Uuid::v7();

        // Create initial setting
        $createCommand = new Command(
            $uuidV7,
            'duplicate.test',
            'initial_value'
        );
        $this->handler->handle($createCommand);
        EntityManagerFactory::get()->clear();

        // Attempt to create the same setting again should throw exception
        $duplicateCommand = new Command(
            $uuidV7,
            'duplicate.test',
            'another_value'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting with key "duplicate.test" already exists.');

        $this->handler->handle($duplicateCommand);
    }

    public function testMultipleSettingsForSameInstallation(): void
    {
        $uuidV7 = Uuid::v7();

        $command1 = new Command($uuidV7, 'setting.one', 'value1');
        $command2 = new Command($uuidV7, 'setting.two', 'value2');

        $this->handler->handle($command1);
        $this->handler->handle($command2);
        EntityManagerFactory::get()->clear();

        $settings = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(2, $settings);
    }

    public function testCanCreatePersonalSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $command = new Command(
            applicationInstallationId: $uuidV7,
            key: 'personal.setting',
            value: 'user_value',
            b24UserId: 123
        );

        $this->handler->handle($command);
        EntityManagerFactory::get()->clear();

        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'personal.setting' && $allSetting->isPersonal()) {
                $setting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($setting);
        $this->assertEquals(123, $setting->getB24UserId());
    }

    public function testCanCreateDepartmentalSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $command = new Command(
            applicationInstallationId: $uuidV7,
            key: 'dept.setting',
            value: 'dept_value',
            b24DepartmentId: 456
        );

        $this->handler->handle($command);
        EntityManagerFactory::get()->clear();

        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'dept.setting' && $allSetting->isDepartmental()) {
                $setting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($setting);
        $this->assertEquals(456, $setting->getB24DepartmentId());
    }
}
