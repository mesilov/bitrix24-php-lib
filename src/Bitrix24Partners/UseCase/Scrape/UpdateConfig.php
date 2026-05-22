<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;

readonly class UpdateConfig
{
    /**
     * @param array<int> $partnerIds
     */
    public function __construct(
        public array $partnerIds,
        public string $outputFile,
        public Bitrix24Zone $zone = Bitrix24Zone::RU,
        public int $delay = 2,
        public bool $insecure = false,
    ) {}
}
