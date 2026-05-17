<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ScrapeResult::class)]
class ScrapeResultTest extends TestCase
{
    #[Test]
    public function testCreatesSuccessfulResult(): void
    {
        $result = new ScrapeResult(
            totalProcessed: 500,
            totalPagesProcessed: 42,
            totalEmptyPages: 0,
            banDetected: false,
        );

        $this->assertSame(500, $result->totalProcessed);
        $this->assertSame(42, $result->totalPagesProcessed);
        $this->assertSame(0, $result->totalEmptyPages);
        $this->assertFalse($result->banDetected);
    }

    #[Test]
    public function testCreatesBannedResult(): void
    {
        $result = new ScrapeResult(
            totalProcessed: 120,
            totalPagesProcessed: 50,
            totalEmptyPages: 30,
            banDetected: true,
        );

        $this->assertTrue($result->banDetected);
    }
}
