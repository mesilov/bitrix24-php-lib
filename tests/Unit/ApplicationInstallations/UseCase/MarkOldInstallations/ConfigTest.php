<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations\MarkOldInstallationsConfig;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MarkOldInstallationsConfig::class)]
class ConfigTest extends TestCase
{
    #[Test]
    #[DataProvider('validTtlProvider')]
    public function testValidTtl(int $ttlInSeconds): void
    {
        $config = new MarkOldInstallationsConfig($ttlInSeconds);

        self::assertSame($ttlInSeconds, $config->ttlInSeconds);
    }

    #[Test]
    public function testNegativeTtlThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MarkOldInstallationsConfig(-1);
    }

    public static function validTtlProvider(): \Generator
    {
        yield 'zero' => [0];
        yield 'one_hour' => [3600];
        yield 'thirty_minutes' => [1800];
    }
}
