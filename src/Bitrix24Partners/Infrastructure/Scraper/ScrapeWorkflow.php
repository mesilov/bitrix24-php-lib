<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use Carbon\CarbonImmutable;
use League\Csv\Writer;
use Psr\Log\LoggerInterface;

class ScrapeWorkflow
{
    public function __construct(
        private readonly PartnerPageScraper $scraper,
        private readonly PartnerCsvStorage $csvStorage,
        private readonly ScrapeStateManager $stateManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param null|\Closure(string): void $onPageRangeProgress
     *
     * @return null|array{startPage: int, lastPage: int, processedNumbers: array<int, true>, partnersPerPage: int}
     */
    public function resolveStartContext(string $baseUrl, string $outputFile, bool $insecure, bool $resume, ?\Closure $onPageRangeProgress = null): ?array
    {
        if ($resume) {
            $resumeState = $this->stateManager->resume($outputFile);
            if (null === $resumeState) {
                return null;
            }

            return [
                'startPage' => $resumeState->startPage,
                'lastPage' => $resumeState->lastPage,
                'processedNumbers' => $resumeState->processedNumbers,
                'partnersPerPage' => 12,
            ];
        }

        $range = $this->scraper->getPageRange($baseUrl, $insecure, $onPageRangeProgress);

        return [
            'startPage' => 1,
            'lastPage' => $range['lastPage'],
            'processedNumbers' => [],
            'partnersPerPage' => $range['partnersPerPage'],
        ];
    }

    public function complete(string $outputFile): void
    {
        $this->stateManager->complete($outputFile);
    }

    /**
     * @param array<int, true>         $processedNumbers
     * @param null|\Closure(int): void $onPageStart
     * @param null|\Closure(int): void $onPartnerStart
     * @param null|\Closure(): void    $onPartnerAdvance
     */
    public function run(
        int $startPage,
        int $lastPage,
        string $baseUrl,
        string $baseDomain,
        bool $insecure,
        int $pageDelay,
        int $partnerDelay,
        string $outputFile,
        bool $resume,
        array &$processedNumbers,
        ?\Closure $onPageStart = null,
        ?\Closure $onPartnerStart = null,
        ?\Closure $onPartnerAdvance = null,
    ): ScrapeResult {
        $this->stateManager->initState($outputFile, $baseUrl, $lastPage);

        $totalProcessed = count($processedNumbers);
        $csvWriter = $resume ? $this->csvStorage->createWriterForResume($outputFile) : $this->csvStorage->createWriter($outputFile);

        $consecutiveEmptyPages = 0;
        $totalEmptyPages = 0;
        $totalPagesProcessed = 0;
        $banDetected = false;

        for ($page = $startPage; $page <= $lastPage; ++$page) {
            $onPageStart?->__invoke($page);

            $partners = [];

            try {
                $partners = $this->scraper->fetchPartnerList($page, $baseUrl, $insecure);
                if ([] === $partners) {
                    $this->logger->warning(sprintf('Страница %d пустая, пропускаем', $page));
                }
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Ошибка при обработке страницы %d: %s', $page, $e->getMessage()));
            }

            ++$totalPagesProcessed;

            if ([] === $partners) {
                ++$consecutiveEmptyPages;
                ++$totalEmptyPages;
            } else {
                $consecutiveEmptyPages = 0;
            }

            if ($consecutiveEmptyPages >= 10) {
                $banDetected = true;
                $this->logger->error(sprintf(
                    'Обнаружена блокировка: %d страниц подряд без данных (страница %d). Рекомендуется увеличить задержки.',
                    $consecutiveEmptyPages,
                    $page
                ));

                break;
            }

            foreach ($partners as $partner) {
                $partnerNumber = $partner['partner_number'];
                $onPartnerStart?->__invoke($partnerNumber);

                if (isset($processedNumbers[$partnerNumber])) {
                    $onPartnerAdvance?->__invoke();

                    continue;
                }

                $this->processPartner(
                    $partner,
                    $baseDomain,
                    $insecure,
                    $csvWriter,
                    $processedNumbers,
                    $totalProcessed
                );

                $onPartnerAdvance?->__invoke();
                $this->stateManager->updateProgress($outputFile, $page);
                sleep($partnerDelay);
            }

            $this->stateManager->updateProgress($outputFile, $page);
            sleep($pageDelay);
        }

        if (!$banDetected && $totalPagesProcessed > 0 && $totalEmptyPages / $totalPagesProcessed > 0.5) {
            $banDetected = true;
            $this->logger->error(sprintf(
                'Подозрение на блокировку: %d из %d страниц пустые (%.0f%%).',
                $totalEmptyPages,
                $totalPagesProcessed,
                $totalEmptyPages / $totalPagesProcessed * 100
            ));
        }

        return new ScrapeResult($totalProcessed, $totalPagesProcessed, $totalEmptyPages, $banDetected);
    }

    /**
     * @param array{partner_number: int, title: string, detail_page_url: string, phone: string} $partner
     * @param array<int, true>                                                                  $processedNumbers
     */
    private function processPartner(
        array $partner,
        string $baseDomain,
        bool $insecure,
        Writer &$csvWriter,
        array &$processedNumbers,
        int &$totalProcessed,
    ): void {
        $partnerNumber = $partner['partner_number'];
        $title = $partner['title'];

        if (isset($processedNumbers[$partnerNumber])) {
            return;
        }

        try {
            $partnerData = $this->scraper->fetchPartnerData($partnerNumber, $baseDomain, $insecure, $title);

            if (null !== $partnerData) {
                $this->csvStorage->writePartner($csvWriter, $partnerData);
            } else {
                $this->csvStorage->writePartner($csvWriter, new PartnerData(
                    bitrix24PartnerNumber: $partnerNumber,
                    title: $title,
                    site: null,
                    phone: $partner['phone'] ?? null,
                    email: null,
                    logoUrl: null,
                    detailPageUrl: $partner['detail_page_url'],
                    baseDomain: $baseDomain,
                    scrapedAt: CarbonImmutable::now(),
                ));
            }

            $processedNumbers[$partnerNumber] = true;
            ++$totalProcessed;
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'Ошибка при обработке партнёра #%d: %s',
                $partnerNumber,
                $e->getMessage()
            ));
        }
    }
}
