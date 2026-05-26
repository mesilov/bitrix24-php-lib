<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeOptions;
use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScrapeOptions::class)]
class ScrapeOptionsTest extends TestCase
{
    #[Test]
    public function isUpdateModeReturnsTrueWithPartnerIds(): void
    {
        $options = new ScrapeOptions(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
            partnerIds: [1, 2, 3],
        );

        $this->assertTrue($options->isUpdateMode());
    }

    #[Test]
    public function isUpdateModeReturnsFalseWithoutPartnerIds(): void
    {
        $options = new ScrapeOptions(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertFalse($options->isUpdateMode());
    }

    #[Test]
    public function isUpdateModeReturnsFalseWithEmptyPartnerIds(): void
    {
        $options = new ScrapeOptions(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
            partnerIds: [],
        );

        $this->assertFalse($options->isUpdateMode());
    }

    #[Test]
    public function isUpdateModeReturnsFalseWithNullPartnerIds(): void
    {
        $options = new ScrapeOptions(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            requestDelay: 2,
            insecure: false,
            resume: false,
            partnerIds: null,
        );

        $this->assertFalse($options->isUpdateMode());
    }

    #[Test]
    public function allPropertiesAccessible(): void
    {
        $options = new ScrapeOptions(
            zone: Bitrix24Zone::KZ,
            outputDir: '/tmp/kz',
            requestDelay: 5,
            insecure: true,
            resume: true,
            partnerIds: [42],
        );

        $this->assertSame(Bitrix24Zone::KZ, $options->zone);
        $this->assertSame('/tmp/kz', $options->outputDir);
        $this->assertSame(5, $options->requestDelay);
        $this->assertTrue($options->insecure);
        $this->assertTrue($options->resume);
        $this->assertSame([42], $options->partnerIds);
    }
}
