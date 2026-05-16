<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerCsvStorage;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerPageScraper;
use Psr\Log\LoggerInterface;

class UpdateWorkflow
{
    public function __construct(
        private readonly PartnerPageScraper $scraper,
        private readonly PartnerCsvStorage $csvStorage,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param null|\Closure(string, int): void $onProgress
     */
    public function run(UpdateConfig $config, ?\Closure $onProgress = null): ScrapeResult
    {
        $csvWriter = $this->csvStorage->createWriter($config->outputFile);
        $totalProcessed = 0;
        $errors = 0;

        foreach ($config->partnerIds as $partnerId) {
            $onProgress?->__invoke('partner_start', $partnerId);

            try {
                $partnerData = $this->scraper->fetchPartnerData(
                    $partnerId,
                    $config->baseDomain,
                    $config->insecure,
                );

                if (null === $partnerData) {
                    $this->logger->warning(sprintf('Партнёр #%d: не удалось загрузить данные', $partnerId));
                    ++$errors;
                } else {
                    $this->csvStorage->writePartner($csvWriter, $partnerData);
                    ++$totalProcessed;
                }
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка обновления партнёра #%d: %s', $partnerId, $e->getMessage()));
                ++$errors;
            }

            $onProgress?->__invoke('partner_advance', 0);
            sleep($config->delay);
        }

        return new ScrapeResult(
            totalProcessed: $totalProcessed,
            totalPagesProcessed: 1,
            totalEmptyPages: $errors,
            banDetected: false,
        );
    }
}
