<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeProgress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScrapeProgress::class)]
class ScrapeProgressTest extends TestCase
{
    #[Test]
    public function startsEmpty(): void
    {
        $progress = new ScrapeProgress();

        $this->assertSame([], $progress->processedNumbers);
        $this->assertSame(0, $progress->totalProcessed);
        $this->assertSame(0, $progress->skippedNoDetailPage);
        $this->assertSame([], $progress->skippedPartnerNumbers);
    }

    #[Test]
    public function startsWithInitialProcessedNumbers(): void
    {
        $progress = new ScrapeProgress([101 => true, 202 => true]);

        $this->assertSame(2, $progress->totalProcessed);
        $this->assertTrue($progress->isProcessed(101));
        $this->assertTrue($progress->isProcessed(202));
        $this->assertFalse($progress->isProcessed(303));
    }

    #[Test]
    public function markProcessedAddsPartnerAndIncrementsTotal(): void
    {
        $progress = new ScrapeProgress();

        $progress->markProcessed(42);

        $this->assertTrue($progress->isProcessed(42));
        $this->assertSame(1, $progress->totalProcessed);
    }

    #[Test]
    public function markSkippedIncrementsCountAndAddsNumber(): void
    {
        $progress = new ScrapeProgress();

        $progress->markSkipped(10);
        $progress->markSkipped(20);

        $this->assertSame(2, $progress->skippedNoDetailPage);
        $this->assertSame([10, 20], $progress->skippedPartnerNumbers);
        $this->assertSame(0, $progress->totalProcessed);
    }

    #[Test]
    public function isProcessedReturnsFalseForUnknown(): void
    {
        $progress = new ScrapeProgress();

        $this->assertFalse($progress->isProcessed(999));
    }

    #[Test]
    public function markProcessedAndSkippedAreIndependent(): void
    {
        $progress = new ScrapeProgress();

        $progress->markProcessed(1);
        $progress->markSkipped(2);
        $progress->markProcessed(3);

        $this->assertSame(2, $progress->totalProcessed);
        $this->assertSame(1, $progress->skippedNoDetailPage);
        $this->assertSame([2], $progress->skippedPartnerNumbers);
    }
}
