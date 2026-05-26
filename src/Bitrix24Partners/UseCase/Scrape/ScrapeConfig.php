<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use Carbon\CarbonImmutable;

readonly class ScrapeConfig
{
    public readonly string $baseUrl;

    /**
     * @param null|array<int> $partnerIds
     */
    public function __construct(
        public Bitrix24Zone $zone,
        public string $outputDir,
        public int $requestDelay,
        public bool $insecure,
        public bool $resume,
        public ?array $partnerIds = null,
        ?string $baseUrl = null,
        public ?string $outputFile = null,
    ) {
        $this->baseUrl = $baseUrl ?? $zone->getPartnerListUrl();
    }

    public function isUpdateMode(): bool
    {
        return null !== $this->partnerIds && [] !== $this->partnerIds;
    }

    public static function getOutputPath(string $outputDir): string
    {
        return rtrim($outputDir, '/').'/partners-'.CarbonImmutable::now()->format('Ymd-His').'.csv';
    }
}
