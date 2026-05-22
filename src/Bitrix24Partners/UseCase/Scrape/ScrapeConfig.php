<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;

readonly class ScrapeConfig
{
    public readonly string $baseUrl;

    public function __construct(
        public Bitrix24Zone $zone,
        public string $outputFile,
        public int $pageDelay,
        public int $partnerDelay,
        public bool $insecure,
        public bool $resume,
        public bool $fullRefresh,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = $baseUrl ?? $zone->getPartnerListUrl();
    }
}
