<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Scrape;

use Bitrix24\Lib\Bitrix24Partners\Services\Scraper\BanDetector;
use Bitrix24\Lib\Bitrix24Partners\Services\Scraper\PartnerPageScraper;
use Bitrix24\Lib\Bitrix24Partners\Services\Scraper\ScrapeStateManager;
use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use League\Csv\Writer;
use Psr\Log\LoggerInterface;

class ScrapeWorkflow
{
    public function __construct(
        private readonly PartnerPageScraper $scraper,
        private readonly ScrapeStateManager $stateManager,
        private readonly BanDetector $banDetector,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return null|array{startPage: int, lastPage: int, processedNumbers: array<int, true>, outputFile: string, zone: string, partnersPerPage: int}
     */
    public function resolveResumeContext(string $outputDir, string $zone): ?array
    {
        return $this->stateManager->resume($outputDir, $zone);
    }

    /**
     * @param null|\Closure(string): void $onVerbose
     *
     * @return array{lastPage: int, partnersPerPage: int}
     */
    public function getPageRange(Bitrix24Zone $zone, bool $insecure, ?\Closure $onVerbose = null): array
    {
        $baseUrl = $zone->getPartnerListUrl();
        $range = $this->scraper->getPageRange($baseUrl, $insecure, $onVerbose);

        return [
            'lastPage' => $range['lastPage'],
            'partnersPerPage' => $range['partnersPerPage'],
        ];
    }

    public function complete(string $outputDir): void
    {
        $this->stateManager->complete($outputDir);
    }

    /**
     * @param null|\Closure(string, int): void $onProgress
     */
    public function runUpdate(ScrapeConfig $config, ?\Closure $onProgress = null): ScrapeResult
    {
        $csvWriter = $this->initCsvWriter($config);

        $totalProcessed = 0;
        $errors = 0;
        $this->banDetector->reset();

        foreach ($config->partnerIds as $partnerId) {
            $onProgress?->__invoke('partner_start', $partnerId);

            try {
                $partnerData = $this->scraper->fetchPartnerData(
                    $partnerId,
                    $config->zone,
                    $config->insecure,
                );

                if (null === $partnerData) {
                    $this->logger->warning(sprintf('Партнёр #%d: детальная страница недоступна', $partnerId));
                    ++$errors;

                    if ($this->banDetector->onEmptyPage()) {
                        break;
                    }
                } else {
                    $this->writePartner($csvWriter, $partnerData);
                    ++$totalProcessed;
                    $this->banDetector->onSuccessfulPage();
                }
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка обновления партнёра #%d: %s', $partnerId, $e->getMessage()));
                ++$errors;

                if ($this->banDetector->onEmptyPage()) {
                    break;
                }
            }

            $onProgress?->__invoke('partner_advance', 0);
            sleep($config->requestDelay);
        }

        return new ScrapeResult(
            totalProcessed: $totalProcessed,
            totalPagesProcessed: $this->banDetector->getTotalPagesProcessed(),
            totalEmptyPages: $errors,
            banDetected: $this->banDetector->isSuspicious(),
        );
    }

    /**
     * @param null|\Closure(string, int): void $onProgress
     */
    public function run(
        ScrapeConfig $config,
        int $startPage,
        int $lastPage,
        int $partnersPerPage,
        ScrapeProgress $progress,
        ?\Closure $onProgress = null,
    ): ScrapeResult {
        if (null === $config->outputFile) {
            throw new \LogicException('outputFile must be set before running scrape.');
        }

        $this->stateManager->initState($config->outputDir, $config->outputFile, $config->baseUrl, $lastPage, $partnersPerPage, $config->zone->value);
        $this->banDetector->reset();

        $csvWriter = $this->initCsvWriter($config);

        $this->scrapePages(
            $config,
            $startPage,
            $lastPage,
            $csvWriter,
            $progress,
            $onProgress,
        );

        return new ScrapeResult(
            $progress->totalProcessed,
            $this->banDetector->getTotalPagesProcessed(),
            $this->banDetector->getTotalEmptyPages(),
            $this->banDetector->isSuspicious(),
            $progress->skippedNoDetailPage,
            $progress->skippedPartnerNumbers,
        );
    }

    private function initCsvWriter(ScrapeConfig $config): Writer
    {
        if (null === $config->outputFile) {
            throw new \LogicException('outputFile must be set before initializing CSV writer.');
        }

        $csvWriter = $config->resume
            ? Writer::from($config->outputFile, 'a+')
            : Writer::from($config->outputFile, 'w+');
        if (!$config->resume) {
            $csvWriter->insertOne([
                'bitrix24_partner_number',
                'title',
                'site',
                'phone',
                'email',
                'logo_url',
                'detail_page_url',
                'zone',
                'scraped_at',
            ]);
        }

        return $csvWriter;
    }

    /**
     * @param null|\Closure(string, int): void $onProgress
     */
    private function scrapePages(
        ScrapeConfig $config,
        int $startPage,
        int $lastPage,
        Writer $csvWriter,
        ScrapeProgress $progress,
        ?\Closure $onProgress = null,
    ): void {
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

            if ([] === $partners) {
                if ($this->banDetector->onEmptyPage()) {
                    break;
                }
            } else {
                $this->banDetector->onSuccessfulPage();
            }

            $this->processPagePartners(
                $page,
                $partners,
                $config,
                $csvWriter,
                $progress,
                $onProgress,
            );

            $this->stateManager->updateProgress($config->outputDir, $page);
            sleep($config->requestDelay);
        }
    }

    /**
     * @param array<int, array{partner_number: int, title: string, detail_page_url: string, phone: string}> $partners
     * @param null|\Closure(string, int): void                                                              $onProgress
     */
    private function processPagePartners(
        int $page,
        array $partners,
        ScrapeConfig $config,
        Writer $csvWriter,
        ScrapeProgress $progress,
        ?\Closure $onProgress = null,
    ): void {
        foreach ($partners as $partner) {
            $partnerNumber = $partner['partner_number'];
            $onProgress?->__invoke('partner_start', $partnerNumber);

            if ($progress->isProcessed($partnerNumber)) {
                $onProgress?->__invoke('partner_advance', 0);

                continue;
            }

            $this->processPartner(
                $partner,
                $config->zone,
                $config->insecure,
                $csvWriter,
                $progress,
            );

            $onProgress?->__invoke('partner_advance', 0);
            $this->stateManager->updateProgress($config->outputDir, $page);
            sleep($config->requestDelay);
        }
    }

    /**
     * @param array{partner_number: int, title: string, detail_page_url: string, phone: string} $partner
     */
    private function processPartner(
        array $partner,
        Bitrix24Zone $zone,
        bool $insecure,
        Writer $csvWriter,
        ScrapeProgress $progress,
    ): void {
        $partnerNumber = $partner['partner_number'];
        $title = $partner['title'];

        if ($progress->isProcessed($partnerNumber)) {
            return;
        }

        try {
            $partnerData = $this->scraper->fetchPartnerData($partnerNumber, $zone, $insecure, $title);

            if (null !== $partnerData) {
                $this->writePartner($csvWriter, $partnerData);
                $progress->markProcessed($partnerNumber);
            } else {
                $progress->markSkipped($partnerNumber);
                $this->logger->warning(sprintf(
                    'Партнёр #%d (%s): детальная страница недоступна, пропускаем',
                    $partnerNumber,
                    $title,
                ));
            }
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf(
                'Ошибка при обработке партнёра #%d: %s',
                $partnerNumber,
                $throwable->getMessage()
            ));
        }
    }

    private function writePartner(Writer $writer, PartnerData $partner): void
    {
        $writer->insertOne([
            $partner->bitrix24PartnerNumber,
            $partner->title,
            $partner->site ?? '',
            $partner->phone ?? '',
            $partner->email ?? '',
            $partner->logoUrl ?? '',
            $partner->detailPageUrl,
            $partner->zone->value,
            $partner->scrapedAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
