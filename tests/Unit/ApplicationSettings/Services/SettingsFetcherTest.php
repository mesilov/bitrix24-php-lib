<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\Tests\Helpers\ApplicationSettings\ApplicationSettingsItemInMemoryRepository;
use Bitrix24\Lib\ApplicationSettings\Services\SettingsFetcher;
use Bitrix24\SDK\Core\Exceptions\ItemNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Test DTO class for deserialization tests.
 */
class TestConfigDto
{
    public function __construct(
        public string $endpoint = '',
        public int $timeout = 30,
        public bool $enabled = true
    ) {}
}

/**
 * Test DTO for string type.
 */
class StringTypeDto
{
    public function __construct(
        public string $value = ''
    ) {}
}

/**
 * Test DTO for boolean type.
 */
class BoolTypeDto
{
    public function __construct(
        public bool $active = false
    ) {}
}

/**
 * Test DTO for int type.
 */
class IntTypeDto
{
    public function __construct(
        public int $count = 0
    ) {}
}

/**
 * Test DTO for float type.
 */
class FloatTypeDto
{
    public function __construct(
        public float $price = 0.0
    ) {}
}

/**
 * Test DTO for DateTimeInterface type.
 */
class DateTimeTypeDto
{
    public function __construct(
        public ?\DateTimeInterface $createdAt = null
    ) {}
}

/**
 * @internal
 */
#[CoversClass(SettingsFetcher::class)]
class SettingsFetcherTest extends TestCase
{
    private ApplicationSettingsItemInMemoryRepository $repository;

    private SettingsFetcher $fetcher;

    private Uuid $installationId;

    private SerializerInterface $serializer;

    /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private LoggerInterface $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ApplicationSettingsItemInMemoryRepository();

        // Create real Symfony Serializer
        $normalizers = [
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(),
        ];
        $encoders = [new JsonEncoder()];

        $this->serializer = new Serializer($normalizers, $encoders);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->fetcher = new SettingsFetcher($this->repository, $this->serializer, $this->logger);
        $this->installationId = Uuid::v7();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    public function testReturnsGlobalSettingWhenNoOverrides(): void
    {
        // Create only global setting
        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSettingsItem);

        $result = $this->fetcher->getItem($this->installationId, 'app.theme');

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalOverridesGlobal(): void
    {
        // Create global and departmental settings
        $globalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // When requesting for department 456, should get departmental setting
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', null, 456);

        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testPersonalOverridesGlobalAndDepartmental(): void
    {
        // Create all three levels
        $globalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $personalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'dark',
            false,
            123 // user ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);
        $this->repository->save($personalSetting);

        // When requesting for user 123 and department 456, should get personal setting
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 123, 456);

        $this->assertEquals('dark', $result->getValue());
        $this->assertTrue($result->isPersonal());
    }

    public function testFallsBackToGlobalWhenPersonalNotFound(): void
    {
        // Only global setting exists
        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSettingsItem);

        // Request for user 123, should fallback to global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 123);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testFallsBackToDepartmentalWhenPersonalNotFound(): void
    {
        // Global and departmental settings exist
        $globalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // Request for user 999 (no personal setting) but department 456
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 999, 456);

        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testThrowsExceptionWhenNoSettingFound(): void
    {
        $this->expectException(ItemNotFoundException::class);
        $this->expectExceptionMessage('Settings item with key "non.existent.key" not found');

        $this->fetcher->getItem($this->installationId, 'non.existent.key');
    }

    public function testGetValueReturnsStringValue(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'app.version',
            '1.2.3',
            false
        );

        $this->repository->save($applicationSettingsItem);

        $result = $this->fetcher->getValue($this->installationId, 'app.version');

        $this->assertEquals('1.2.3', $result);
    }

    public function testGetValueThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(ItemNotFoundException::class);
        $this->expectExceptionMessage('Settings item with key "non.existent" not found');

        $this->fetcher->getValue($this->installationId, 'non.existent');
    }

    public function testGetValueDeserializesToObject(): void
    {
        $jsonValue = json_encode([
            'endpoint' => 'https://api.example.com',
            'timeout' => 60,
            'enabled' => true,
        ]);

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'api.config',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $testConfigDto = $this->fetcher->getValue(
            $this->installationId,
            'api.config',
            class: TestConfigDto::class
        );

        $this->assertInstanceOf(TestConfigDto::class, $testConfigDto);
        $this->assertEquals('https://api.example.com', $testConfigDto->endpoint);
        $this->assertEquals(60, $testConfigDto->timeout);
        $this->assertTrue($testConfigDto->enabled);
    }

    public function testGetValueWithoutClassReturnsRawString(): void
    {
        $jsonValue = '{"foo":"bar","baz":123}';

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'raw.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $result = $this->fetcher->getValue($this->installationId, 'raw.setting');

        $this->assertIsString($result);
        $this->assertEquals($jsonValue, $result);
    }

    public function testGetValueLogsDeserializationFailure(): void
    {
        $jsonValue = 'invalid json{';

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'broken.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('SettingsFetcher.getValue.deserializationFailed', $this->callback(fn($context): bool => isset($context['key'], $context['class'], $context['error'])
                && 'broken.setting' === $context['key']
                && TestConfigDto::class === $context['class']));

        $this->expectException(\Throwable::class);

        $this->fetcher->getValue(
            $this->installationId,
            'broken.setting',
            class: TestConfigDto::class
        );
    }

    public function testPersonalSettingForDifferentUserNotUsed(): void
    {
        // Create global and personal for user 123
        $globalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $personalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'dark',
            false,
            123 // user ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($personalSetting);

        // Request for user 456 (different user), should get global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 456);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalSettingForDifferentDepartmentNotUsed(): void
    {
        // Create global and departmental for dept 456
        $globalSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // Request for dept 789 (different department), should get global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', null, 789);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testGetValueDeserializesStringType(): void
    {
        $jsonValue = json_encode(['value' => 'test string']);

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'string.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $stringTypeDto = $this->fetcher->getValue(
            $this->installationId,
            'string.setting',
            class: StringTypeDto::class
        );

        $this->assertInstanceOf(StringTypeDto::class, $stringTypeDto);
        $this->assertEquals('test string', $stringTypeDto->value);
    }

    public function testGetValueDeserializesBoolType(): void
    {
        $jsonValue = json_encode(['active' => true]);

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'bool.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $boolTypeDto = $this->fetcher->getValue(
            $this->installationId,
            'bool.setting',
            class: BoolTypeDto::class
        );

        $this->assertInstanceOf(BoolTypeDto::class, $boolTypeDto);
        $this->assertTrue($boolTypeDto->active);

        // Test with false
        $jsonValueFalse = json_encode(['active' => false]);
        $applicationSettingsItemFalse = new ApplicationSettingsItem(
            $this->installationId,
            'bool.setting.false',
            $jsonValueFalse,
            false
        );
        $this->repository->save($applicationSettingsItemFalse);

        $resultFalse = $this->fetcher->getValue(
            $this->installationId,
            'bool.setting.false',
            class: BoolTypeDto::class
        );

        $this->assertInstanceOf(BoolTypeDto::class, $resultFalse);
        $this->assertFalse($resultFalse->active);
    }

    public function testGetValueDeserializesIntType(): void
    {
        $jsonValue = json_encode(['count' => 42]);

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'int.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $intTypeDto = $this->fetcher->getValue(
            $this->installationId,
            'int.setting',
            class: IntTypeDto::class
        );

        $this->assertInstanceOf(IntTypeDto::class, $intTypeDto);
        $this->assertIsInt($intTypeDto->count);
        $this->assertEquals(42, $intTypeDto->count);
    }

    public function testGetValueDeserializesFloatType(): void
    {
        $jsonValue = json_encode(['price' => 99.99]);

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'float.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $floatTypeDto = $this->fetcher->getValue(
            $this->installationId,
            'float.setting',
            class: FloatTypeDto::class
        );

        $this->assertInstanceOf(FloatTypeDto::class, $floatTypeDto);
        $this->assertIsFloat($floatTypeDto->price);
        $this->assertEquals(99.99, $floatTypeDto->price);
    }

    public function testGetValueDeserializesDateTimeType(): void
    {
        $dateTime = new \DateTimeImmutable('2025-01-15 10:30:00');
        $jsonValue = json_encode(['createdAt' => $dateTime->format(\DateTimeInterface::RFC3339)]);

        $applicationSettingsItem = new ApplicationSettingsItem(
            $this->installationId,
            'datetime.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $dateTimeTypeDto = $this->fetcher->getValue(
            $this->installationId,
            'datetime.setting',
            class: DateTimeTypeDto::class
        );

        $this->assertInstanceOf(DateTimeTypeDto::class, $dateTimeTypeDto);
        $this->assertInstanceOf(\DateTimeInterface::class, $dateTimeTypeDto->createdAt);
        $this->assertEquals('2025-01-15', $dateTimeTypeDto->createdAt->format('Y-m-d'));
        $this->assertEquals('10:30:00', $dateTimeTypeDto->createdAt->format('H:i:s'));
    }
}
