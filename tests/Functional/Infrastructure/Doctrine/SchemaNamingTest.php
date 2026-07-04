<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Infrastructure\Doctrine;

use Bitrix24\Lib\Tests\EntityManagerFactory;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SchemaNamingTest extends TestCase
{
    #[Test]
    public function testDoctrineSchemaUsesNormalizedTableNames(): void
    {
        $schemaManager = EntityManagerFactory::get()->getConnection()->createSchemaManager();
        $tableNames = array_map(
            static fn (OptionallyQualifiedName $optionallyQualifiedName): string => trim($optionallyQualifiedName->toString(), '"'),
            $schemaManager->introspectTableNames()
        );

        $expectedTableNames = [
            'b24lib_application_installations',
            'b24lib_application_settings',
            'b24lib_bitrix24_accounts',
            'b24lib_contact_persons',
        ];

        $legacyTableNames = [
            'application_installation',
            'application_settings',
            'bitrix24account',
            'contact_person',
        ];

        foreach ($expectedTableNames as $expectedTableName) {
            self::assertContains($expectedTableName, $tableNames);
        }

        foreach ($legacyTableNames as $legacyTableName) {
            self::assertNotContains($legacyTableName, $tableNames);
        }
    }

    #[Test]
    public function testApplicationSettingsIndexesAndConstraintUseNormalizedNames(): void
    {
        $connection = EntityManagerFactory::get()->getConnection();

        $indexNames = $connection->fetchFirstColumn(<<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'b24lib_application_settings'
            ORDER BY indexname
            SQL);

        $expectedIndexNames = [
            'b24lib_application_settings_idx_application_installation_id',
            'b24lib_application_settings_idx_b24_department_id',
            'b24lib_application_settings_idx_b24_user_id',
            'b24lib_application_settings_idx_key',
            'b24lib_application_settings_idx_status',
            'b24lib_application_settings_unique_scope',
        ];

        $legacyIndexNames = [
            'idx_application_installation_id',
            'idx_b24_department_id',
            'idx_b24_user_id',
            'idx_key',
            'idx_status',
            'unique_app_setting_scope',
        ];

        foreach ($expectedIndexNames as $expectedIndexName) {
            self::assertContains($expectedIndexName, $indexNames);
        }

        foreach ($legacyIndexNames as $legacyIndexName) {
            self::assertNotContains($legacyIndexName, $indexNames);
        }
    }
}
