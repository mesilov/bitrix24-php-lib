<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/** All lifecycle writers must acquire the same key before reading or mutating its records. */
final class LifecycleLock
{
    public static function acquire(Connection $connection, string $key): void
    {
        if (!$connection->isTransactionActive() || !$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            throw new \LogicException('Lifecycle locks require a PostgreSQL transaction.');
        }

        $parts = unpack('Nfirst/Nsecond', substr(hash('sha256', 'b24lib:'.$key, true), 0, 8));
        assert(is_array($parts));
        $first = $parts['first'] > 0x7FFFFFFF ? $parts['first'] - 0x100000000 : $parts['first'];
        $second = $parts['second'] > 0x7FFFFFFF ? $parts['second'] - 0x100000000 : $parts['second'];
        $connection->executeQuery('SELECT pg_advisory_xact_lock(?, ?)', [$first, $second]);
    }
}
