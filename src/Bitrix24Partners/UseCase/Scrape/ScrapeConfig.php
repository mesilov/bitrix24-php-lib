<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;

readonly class ScrapeConfig
{
    public readonly string $baseUrl;

    /**
     * @param null|array<int> $partnerIds
     */
    public function __construct(
        public Bitrix24Zone $zone,
        public string $outputFile,
        public int $catalogPageDelay,
        public int $partnerDetailDelay,
        public bool $insecure,
        public bool $resume,
        public bool $fullRefresh,
        public ?array $partnerIds = null,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = $baseUrl ?? $zone->getPartnerListUrl();
    }

    public function isUpdateMode(): bool
    {
        return null !== $this->partnerIds && [] !== $this->partnerIds;
    }
}
