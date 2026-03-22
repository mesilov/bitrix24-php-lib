<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Infrastructure\InMemory;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\Lib\Tests\Contract\ApplicationSettings\Infrastructure\ApplicationSettingsItemRepositoryInterfaceContractTest;
use Bitrix24\Lib\Tests\Helpers\ApplicationSettings\ApplicationSettingsItemInMemoryRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Contract test implementation for InMemory repository.
 *
 * @internal
 */
#[CoversClass(ApplicationSettingsItemInMemoryRepository::class)]
class ApplicationSettingsItemInMemoryRepositoryContractTest extends ApplicationSettingsItemRepositoryInterfaceContractTest
{
    #[\Override]
    protected function createRepository(): ApplicationSettingsItemRepositoryInterface
    {
        return new ApplicationSettingsItemInMemoryRepository();
    }

    #[\Override]
    protected function clearRepository(): void
    {
        if ($this->repository instanceof ApplicationSettingsItemInMemoryRepository) {
            $this->repository->clear();
        }
    }
}
