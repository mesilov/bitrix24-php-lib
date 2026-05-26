<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;

readonly class ScrapeOptions
{
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
    ) {}

    public function isUpdateMode(): bool
    {
        return null !== $this->partnerIds && [] !== $this->partnerIds;
    }
}
