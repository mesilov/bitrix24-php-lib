<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

readonly class ScrapeResult
{
    public function __construct(
        public int $totalProcessed,
        public int $totalPagesProcessed,
        public int $totalEmptyPages,
        public bool $banDetected,
    ) {}
}
