<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
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
        public Bitrix24Zone $zone,
        public CarbonImmutable $scrapedAt,
    ) {}
}
