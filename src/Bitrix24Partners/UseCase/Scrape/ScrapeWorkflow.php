<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerCsvStorage;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerPageScraper;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\ScrapeStateManager;
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
     * @param null|\Closure(string): void $onVerbose
     *
     * @return null|array{startPage: int, lastPage: int, processedNumbers: array<int, true>, partnersPerPage: int}
     */
    public function resolveStartContext(ScrapeConfig $config, ?\Closure $onVerbose = null): ?array
    {
        if ($config->resume) {
            $resumeState = $this->stateManager->resume($config->outputFile);
            if (null === $resumeState) {
                return null;
            }

            return [
                'startPage' => $resumeState['startPage'],
                'lastPage' => $resumeState['lastPage'],
                'processedNumbers' => $resumeState['processedNumbers'],
                'partnersPerPage' => 12,
            ];
        }

        $range = $this->scraper->getPageRange($config->baseUrl, $config->insecure, $onVerbose);

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
     * @param array<int, true>                 $initialProcessedNumbers
     * @param null|\Closure(string, int): void $onProgress
     */
    public function run(
        ScrapeConfig $config,
        int $startPage,
        int $lastPage,
        array $initialProcessedNumbers,
        ?\Closure $onProgress = null,
    ): ScrapeResult {
        $this->stateManager->initState($config->outputFile, $config->baseUrl, $lastPage);

        $processedNumbers = $initialProcessedNumbers;
        $totalProcessed = count($processedNumbers);
        $csvWriter = $config->resume
            ? $this->csvStorage->createWriterForResume($config->outputFile)
            : $this->csvStorage->createWriter($config->outputFile);

        $consecutiveEmptyPages = 0;
        $totalEmptyPages = 0;
        $totalPagesProcessed = 0;
        $banDetected = false;

        for ($page = $startPage; $page <= $lastPage; ++$page) {
            $onProgress?->__invoke('page_start', $page);

            $partners = [];

            try {
                $partners = $this->scraper->fetchPartnerList($page, $config->baseUrl, $config->insecure);
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
                $onProgress?->__invoke('partner_start', $partnerNumber);

                if (isset($processedNumbers[$partnerNumber])) {
                    $onProgress?->__invoke('partner_advance', 0);

                    continue;
                }

                $this->processPartner(
                    $partner,
                    $config->baseDomain,
                    $config->insecure,
                    $csvWriter,
                    $processedNumbers,
                    $totalProcessed
                );

                $onProgress?->__invoke('partner_advance', 0);
                $this->stateManager->updateProgress($config->outputFile, $page);
                sleep($config->partnerDelay);
            }

            $this->stateManager->updateProgress($config->outputFile, $page);
            sleep($config->pageDelay);
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
        Writer $csvWriter,
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
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf(
                'Ошибка при обработке партнёра #%d: %s',
                $partnerNumber,
                $throwable->getMessage()
            ));
        }
    }
}
