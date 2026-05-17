<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

readonly class UpdateConfig
{
    public readonly string $baseDomain;

    /**
     * @param array<int> $partnerIds
     */
    public function __construct(
        public array $partnerIds,
        public string $outputFile,
        string $baseDomain = 'https://www.bitrix24.ru',
        public int $delay = 2,
        public bool $insecure = false,
    ) {
        $this->baseDomain = rtrim($baseDomain, '/');
    }
}
