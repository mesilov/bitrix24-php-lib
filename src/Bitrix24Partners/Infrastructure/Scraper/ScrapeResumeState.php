<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

readonly class ScrapeResumeState
{
    /**
     * @param array<int, true> $processedNumbers
     */
    public function __construct(
        public int $lastPage,
        public int $startPage,
        public array $processedNumbers,
    ) {}
}
