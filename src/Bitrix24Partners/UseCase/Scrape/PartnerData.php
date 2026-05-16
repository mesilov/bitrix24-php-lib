<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Carbon\CarbonImmutable;

readonly class PartnerData
{
    public function __construct(
        public int $bitrix24PartnerNumber,
        public string $title,
        public ?string $site,
        public ?string $phone,
        public ?string $email,
        public ?string $logoUrl,
        public string $detailPageUrl,
        public string $baseDomain,
        public CarbonImmutable $scrapedAt,
    ) {}
}
