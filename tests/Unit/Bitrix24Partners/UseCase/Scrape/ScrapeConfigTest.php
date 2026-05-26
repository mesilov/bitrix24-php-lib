<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeConfig;
use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScrapeConfig::class)]
class ScrapeConfigTest extends TestCase
{
    #[Test]
    public function outputFileIsNullByDefault(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertNull($config->outputFile);
    }

    #[Test]
    public function outputFileCanBeSetExplicitly(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
            outputFile: '/tmp/test/partners-20260526-120000.csv',
        );

        $this->assertSame('/tmp/test/partners-20260526-120000.csv', $config->outputFile);
    }

    #[Test]
    public function getOutputPathProducesPathWithDir(): void
    {
        $path = ScrapeConfig::getOutputPath('/tmp/test');

        $this->assertStringStartsWith('/tmp/test/partners-', $path);
        $this->assertStringEndsWith('.csv', $path);
    }

    #[Test]
    public function getOutputPathStripsTrailingSlash(): void
    {
        $path = ScrapeConfig::getOutputPath('/tmp/test/');

        $this->assertStringStartsWith('/tmp/test/partners-', $path);
        $this->assertDoesNotMatchRegularExpression('|//partners-|', $path);
    }

    #[Test]
    public function baseUrlDefaultsToZoneUrl(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertSame(Bitrix24Zone::RU->getPartnerListUrl(), $config->baseUrl);
    }

    #[Test]
    public function isUpdateModeReturnsTrueWithPartnerIds(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
            partnerIds: [1, 2, 3],
        );

        $this->assertTrue($config->isUpdateMode());
    }

    #[Test]
    public function isUpdateModeReturnsFalseWithoutPartnerIds(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertFalse($config->isUpdateMode());
    }
}
