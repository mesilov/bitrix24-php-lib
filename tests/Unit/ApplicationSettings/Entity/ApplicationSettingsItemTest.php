<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Entity;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationSettingsItem::class)]
class ApplicationSettingsItemTest extends TestCase
{
    public function testCanCreateGlobalSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $key = 'test.setting.key';
        $value = '{"foo":"bar"}';

        $applicationSettingsItem = new ApplicationSettingsItem($uuidV7, $key, $value, false);

        $this->assertInstanceOf(Uuid::class, $applicationSettingsItem->getId());
        $this->assertEquals($uuidV7, $applicationSettingsItem->getApplicationInstallationId());
        $this->assertEquals($key, $applicationSettingsItem->getKey());
        $this->assertEquals($value, $applicationSettingsItem->getValue());
        $this->assertNull($applicationSettingsItem->getB24UserId());
        $this->assertNull($applicationSettingsItem->getB24DepartmentId());
        $this->assertTrue($applicationSettingsItem->isGlobal());
        $this->assertFalse($applicationSettingsItem->isPersonal());
        $this->assertFalse($applicationSettingsItem->isDepartmental());
        $this->assertFalse($applicationSettingsItem->isRequired());
    }

    public function testCanCreatePersonalSetting(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'user.preference',
            'dark_mode',
            false, // isRequired
            123 // b24UserId
        );

        $this->assertEquals(123, $applicationSettingsItem->getB24UserId());
        $this->assertNull($applicationSettingsItem->getB24DepartmentId());
        $this->assertFalse($applicationSettingsItem->isGlobal());
        $this->assertTrue($applicationSettingsItem->isPersonal());
        $this->assertFalse($applicationSettingsItem->isDepartmental());
    }

    public function testCanCreateDepartmentalSetting(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'dept.config',
            'enabled',
            false, // isRequired
            null,  // No user ID
            456    // b24DepartmentId
        );

        $this->assertNull($applicationSettingsItem->getB24UserId());
        $this->assertEquals(456, $applicationSettingsItem->getB24DepartmentId());
        $this->assertFalse($applicationSettingsItem->isGlobal());
        $this->assertFalse($applicationSettingsItem->isPersonal());
        $this->assertTrue($applicationSettingsItem->isDepartmental());
    }

    public function testCannotCreateSettingWithBothUserAndDepartment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting cannot be both personal and departmental');

        new ApplicationSettingsItem(
            Uuid::v7(),
            'invalid.setting',
            'value',
            false, // isRequired
            123,   // userId
            456    // departmentId - both set, should fail
        );
    }

    public function testCanUpdateValue(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'test.key',
            'initial.value',
            false
        );

        $initialUpdatedAt = $applicationSettingsItem->getUpdatedAt();
        usleep(1000);

        $applicationSettingsItem->updateValue('new.value');

        $this->assertEquals('new.value', $applicationSettingsItem->getValue());
        $this->assertGreaterThan($initialUpdatedAt, $applicationSettingsItem->getUpdatedAt());
    }

    #[DataProvider('invalidKeyProvider')]
    public function testThrowsExceptionForInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApplicationSettingsItem(
            Uuid::v7(),
            $invalidKey,
            'value',
            false
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidKeyProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['   '],
            'too long' => [str_repeat('a', 256)],
            'with uppercase' => ['Test.Key'],
            'with numbers' => ['test.key.123'],
            'with underscore' => ['test_key'],
            'with hyphen' => ['test-key'],
            'spaces' => ['invalid key'],
            'special chars' => ['key@#$%'],
        ];
    }

    #[DataProvider('validKeyProvider')]
    public function testAcceptsValidKeys(string $validKey): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $validKey,
            'value',
            false
        );

        $this->assertEquals($validKey, $applicationSettingsItem->getKey());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validKeyProvider(): array
    {
        return [
            'simple lowercase' => ['key'],
            'with dots' => ['app.setting.key'],
            'multiple dots' => ['a.b.c.d.e'],
            'single char' => ['a'],
            'long valid key' => ['very.long.setting.key.name'],
        ];
    }

    public function testThrowsExceptionForInvalidUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 user ID must be positive integer');

        new ApplicationSettingsItem(
            Uuid::v7(),
            'test.key',
            'value',
            false, // isRequired
            0      // Invalid: zero
        );
    }

    public function testThrowsExceptionForNegativeUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 user ID must be positive integer');

        new ApplicationSettingsItem(
            Uuid::v7(),
            'test.key',
            'value',
            false, // isRequired
            -1     // Invalid: negative
        );
    }

    public function testThrowsExceptionForInvalidDepartmentId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 department ID must be positive integer');

        new ApplicationSettingsItem(
            Uuid::v7(),
            'test.key',
            'value',
            false, // isRequired
            null,  // No user ID
            0      // Invalid: zero
        );
    }

    public function testCanCreateRequiredSetting(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'required.setting',
            'value',
            true // isRequired
        );

        $this->assertTrue($applicationSettingsItem->isRequired());
    }

    public function testCanTrackWhoChangedSetting(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'tracking.test',
            'initial.value',
            false,
            null,
            null,
            123 // changedByBitrix24UserId
        );

        $this->assertEquals(123, $applicationSettingsItem->getChangedByBitrix24UserId());

        // Update value with different user
        $applicationSettingsItem->updateValue('new.value', 456);

        $this->assertEquals(456, $applicationSettingsItem->getChangedByBitrix24UserId());
        $this->assertEquals('new.value', $applicationSettingsItem->getValue());
    }

    public function testDefaultStatusIsActive(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'status.test',
            'value',
            false
        );

        $this->assertTrue($applicationSettingsItem->isActive());
    }

    public function testCanMarkAsDeleted(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'delete.test',
            'value',
            false
        );

        $this->assertTrue($applicationSettingsItem->isActive());

        $initialUpdatedAt = $applicationSettingsItem->getUpdatedAt();
        usleep(1000);
        $applicationSettingsItem->markAsDeleted();

        $this->assertFalse($applicationSettingsItem->isActive());
        $this->assertGreaterThan($initialUpdatedAt, $applicationSettingsItem->getUpdatedAt());
    }

    public function testMarkAsDeletedIsIdempotent(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            'idempotent.test',
            'value',
            false
        );

        $applicationSettingsItem->markAsDeleted();

        $firstUpdatedAt = $applicationSettingsItem->getUpdatedAt();

        usleep(1000);
        $applicationSettingsItem->markAsDeleted(); // Second call should not change updatedAt

        $this->assertEquals($firstUpdatedAt, $applicationSettingsItem->getUpdatedAt());
    }
}
