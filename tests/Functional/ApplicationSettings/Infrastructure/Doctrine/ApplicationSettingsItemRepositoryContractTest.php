<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\Lib\Tests\Contract\ApplicationSettings\Infrastructure\ApplicationSettingsItemRepositoryInterfaceContractTest;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Contract test implementation for Doctrine repository.
 *
 * @internal
 */
#[CoversClass(ApplicationSettingsItemRepository::class)]
class ApplicationSettingsItemRepositoryContractTest extends ApplicationSettingsItemRepositoryInterfaceContractTest
{
    #[\Override]
    protected function createRepository(): ApplicationSettingsItemRepositoryInterface
    {
        $entityManager = EntityManagerFactory::get();

        return new ApplicationSettingsItemRepository($entityManager);
    }

    #[\Override]
    protected function flushChanges(): void
    {
        EntityManagerFactory::get()->flush();
    }

    #[\Override]
    protected function clearRepository(): void
    {
        // Clear entity manager between tests
        EntityManagerFactory::get()->clear();
    }

    #[\Override]
    protected function tearDown(): void
    {
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();
        parent::tearDown();
    }
}
