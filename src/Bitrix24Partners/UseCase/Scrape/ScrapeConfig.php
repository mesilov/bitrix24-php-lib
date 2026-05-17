<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

readonly class ScrapeConfig
{
    public readonly string $baseDomain;

    public function __construct(
        public string $baseUrl,
        public string $outputFile,
        public int $pageDelay,
        public int $partnerDelay,
        public bool $insecure,
        public bool $resume,
        public bool $fullRefresh,
    ) {
        $parsed = parse_url($baseUrl);
        $this->baseDomain = ($parsed['scheme'] ?? 'https').'://'.$parsed['host'];
    }
}
