<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\BanDetector;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerPageScraper;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\ScrapeStateManager;
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

        $this->banDetector->reset();

        $skippedNoDetailPage = 0;
        $skippedPartnerNumbers = [];

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

            foreach ($partners as $partner) {
                $partnerNumber = $partner['partner_number'];
                $onProgress?->__invoke('partner_start', $partnerNumber);

                if (isset($processedNumbers[$partnerNumber])) {
                    $onProgress?->__invoke('partner_advance', 0);

                    continue;
                }

                $this->processPartner(
                    $partner,
                    $config->zone,
                    $config->insecure,
                    $csvWriter,
                    $processedNumbers,
                    $totalProcessed,
                    $skippedNoDetailPage,
                    $skippedPartnerNumbers,
                );

                $onProgress?->__invoke('partner_advance', 0);
                $this->stateManager->updateProgress($config->outputFile, $page);
                sleep($config->partnerDelay);
            }

            $this->stateManager->updateProgress($config->outputFile, $page);
            sleep($config->pageDelay);
        }

        $banDetected = $this->banDetector->isSuspicious();

        return new ScrapeResult(
            $totalProcessed,
            $this->banDetector->getTotalPagesProcessed(),
            $this->banDetector->getTotalEmptyPages(),
            $banDetected,
            $skippedNoDetailPage,
            $skippedPartnerNumbers,
        );
    }

    /**
     * @param array{partner_number: int, title: string, detail_page_url: string, phone: string} $partner
     * @param array<int, true>                                                                  $processedNumbers
     * @param array<int>                                                                        $skippedPartnerNumbers
     */
    private function processPartner(
        array $partner,
        Bitrix24Zone $zone,
        bool $insecure,
        Writer $csvWriter,
        array &$processedNumbers,
        int &$totalProcessed,
        int &$skippedNoDetailPage,
        array &$skippedPartnerNumbers,
    ): void {
        $partnerNumber = $partner['partner_number'];
        $title = $partner['title'];

        if (isset($processedNumbers[$partnerNumber])) {
            return;
        }

        try {
            $partnerData = $this->scraper->fetchPartnerData($partnerNumber, $zone, $insecure, $title);

            if (null !== $partnerData) {
                $this->writePartner($csvWriter, $partnerData);
                $processedNumbers[$partnerNumber] = true;
                ++$totalProcessed;
            } else {
                ++$skippedNoDetailPage;
                $skippedPartnerNumbers[] = $partnerNumber;
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
