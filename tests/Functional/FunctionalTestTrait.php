<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional;

use Bitrix24\Lib\Tests\EntityManagerFactory;

trait FunctionalTestTrait
{
    protected function truncateBitrix24Partners(): void
    {
        EntityManagerFactory::reset();
        $entityManager = EntityManagerFactory::get();
        $connection = $entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE b24lib_bitrix24_partners RESTART IDENTITY CASCADE');

        $entityManager->clear();
    }
}
