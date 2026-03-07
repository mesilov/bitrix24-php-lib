<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\UseCase\Delete;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\ApplicationSettings\UseCase\Delete\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Delete\Handler;
use Bitrix24\SDK\Core\Exceptions\ItemNotFoundException;
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

    public function testCanDeleteExistingSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'delete.test',
            'value',
            false
        );

        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $command = new Command($uuidV7, 'delete.test');
        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        // Setting should not be found by regular find methods (soft-deleted)
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $deletedSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'delete.test' && $allSetting->isGlobal()) {
                $deletedSetting = $allSetting;
                break;
            }
        }

        $this->assertNull($deletedSetting);

        // But should still exist in database with deleted status
        $settingById = EntityManagerFactory::get()
            ->createQueryBuilder()
            ->select('s')
            ->from(\Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem::class, 's')
            ->where('s.applicationInstallationId = :appId')
            ->andWhere('s.key = :key')
            ->setParameter('appId', $uuidV7)
            ->setParameter('key', 'delete.test')
            ->getQuery()
            ->getOneOrNullResult();

        $this->assertNotNull($settingById);
        $this->assertFalse($settingById->isActive());
    }

    public function testThrowsExceptionForNonExistentSetting(): void
    {
        $command = new Command(Uuid::v7(), 'non.existent');

        $this->expectException(ItemNotFoundException::class);
        $this->expectExceptionMessage('Setting with key "non.existent" not found.');

        $this->handler->handle($command);
    }
}
