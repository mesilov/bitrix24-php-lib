<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

class ScrapeStateManager
{
    public function __construct(
        private readonly PartnerCsvStorage $csvStorage,
    ) {}

    public function getStateFilePath(string $outputFile): string
    {
        return $outputFile.'.state.json';
    }

    /**
     * @return null|array{mode: string, base_url: string, total_pages: int, last_completed_page: int, output_file: string, started_at: string, updated_at: string}
     */
    public function read(string $outputFile): ?array
    {
        $statePath = $this->getStateFilePath($outputFile);
        if (!file_exists($statePath)) {
            return null;
        }

        $content = file_get_contents($statePath);
        if (false === $content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param array{mode: string, base_url: string, total_pages: int, last_completed_page: int, output_file: string, started_at: string, updated_at: string} $state
     */
    public function write(string $outputFile, array $state): void
    {
        $state['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        file_put_contents(
            $this->getStateFilePath($outputFile),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function delete(string $outputFile): void
    {
        $statePath = $this->getStateFilePath($outputFile);
        if (file_exists($statePath)) {
            unlink($statePath);
        }
    }

    /**
     * @return array<int, true>
     */
    public function loadProcessedPartnerNumbers(string $outputFile): array
    {
        if (!file_exists($outputFile)) {
            return [];
        }

        $partnerMap = $this->csvStorage->readAsPartnerMap($outputFile);

        return array_fill_keys(array_keys($partnerMap), true);
    }
}
