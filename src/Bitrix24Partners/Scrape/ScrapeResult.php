<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Scrape;

readonly class ScrapeResult
{
    /**
     * @param array<int> $skippedPartnerNumbers
     */
    public function __construct(
        public int $totalProcessed,
        public int $totalPagesProcessed,
        public int $totalEmptyPages,
        public bool $banDetected,
        public int $skippedNoDetailPage = 0,
        public array $skippedPartnerNumbers = [],
    ) {}
}
