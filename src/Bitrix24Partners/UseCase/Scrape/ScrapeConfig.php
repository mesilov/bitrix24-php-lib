<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;

readonly class ScrapeConfig
{
    public readonly string $baseUrl;

    public readonly string $baseDomain;

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
        $this->baseDomain = $zone->getBaseDomain();
        $this->baseUrl = $baseUrl ?? $this->buildDefaultListUrl($zone);
    }

    private function buildDefaultListUrl(Bitrix24Zone $zone): string
    {
        return match ($zone) {
            Bitrix24Zone::RU => 'https://www.bitrix24.ru/partners/country__19/',
            Bitrix24Zone::KZ => 'https://www.bitrix24.kz/partners/country__36/',
        };
    }
}
