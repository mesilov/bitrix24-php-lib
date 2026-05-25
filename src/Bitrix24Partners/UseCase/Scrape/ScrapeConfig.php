<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use Carbon\CarbonImmutable;

readonly class ScrapeConfig
{
    public readonly string $baseUrl;

    public readonly string $outputFile;

    /**
     * @param null|array<int> $partnerIds
     */
    public function __construct(
        public Bitrix24Zone $zone,
        public string $outputDir,
        public int $catalogPageDelay,
        public int $partnerDetailDelay,
        public bool $insecure,
        public bool $resume,
        public ?array $partnerIds = null,
        ?string $baseUrl = null,
        ?string $outputFile = null,
    ) {
        $this->baseUrl = $baseUrl ?? $zone->getPartnerListUrl();
        $this->outputFile = $outputFile ?? self::generateTimestampedPath($outputDir);
    }

    public function isUpdateMode(): bool
    {
        return null !== $this->partnerIds && [] !== $this->partnerIds;
    }

    public static function generateTimestampedPath(string $outputDir): string
    {
        return rtrim($outputDir, '/').'/partners-'.CarbonImmutable::now()->format('Ymd-His').'.csv';
    }
}
