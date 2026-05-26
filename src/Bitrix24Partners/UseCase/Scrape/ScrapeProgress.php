<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

class ScrapeProgress
{
    /** @var array<int, true> */
    public array $processedNumbers = [];

    public int $totalProcessed = 0;

    public int $skippedNoDetailPage = 0;

    /** @var array<int> */
    public array $skippedPartnerNumbers = [];

    /**
     * @param array<int, true> $initialProcessedNumbers
     */
    public function __construct(array $initialProcessedNumbers = [])
    {
        $this->processedNumbers = $initialProcessedNumbers;
        $this->totalProcessed = count($initialProcessedNumbers);
    }

    public function markProcessed(int $partnerNumber): void
    {
        $this->processedNumbers[$partnerNumber] = true;
        ++$this->totalProcessed;
    }

    public function markSkipped(int $partnerNumber): void
    {
        ++$this->skippedNoDetailPage;
        $this->skippedPartnerNumbers[] = $partnerNumber;
    }

    public function isProcessed(int $partnerNumber): bool
    {
        return isset($this->processedNumbers[$partnerNumber]);
    }
}
