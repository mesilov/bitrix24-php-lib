<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

class ScrapeStateManager
{
    private ?array $state = null;

    public function __construct(
        private readonly PartnerCsvStorage $csvStorage,
    ) {}

    /**
     * @return null|array{lastPage: int, startPage: int, processedNumbers: array<int, true>}
     */
    public function resume(string $outputFile): ?array
    {
        $state = $this->readStateFile($outputFile);
        if (null === $state) {
            return null;
        }

        $processedNumbers = $this->loadProcessedPartnerNumbers($outputFile);

        return [
            'lastPage' => $state['total_pages'],
            'startPage' => $state['last_completed_page'] + 1,
            'processedNumbers' => $processedNumbers,
        ];
    }

    public function initState(string $outputFile, string $baseUrl, int $lastPage): void
    {
        $this->state = [
            'mode' => 'full_scrape',
            'base_url' => $baseUrl,
            'total_pages' => $lastPage,
            'last_completed_page' => 0,
            'output_file' => $outputFile,
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updated_at' => '',
        ];
        $this->writeState($outputFile);
    }

    public function updateProgress(string $outputFile, int $completedPage): void
    {
        if (null === $this->state) {
            return;
        }

        $this->state['last_completed_page'] = $completedPage;
        $this->writeState($outputFile);
    }

    public function complete(string $outputFile): void
    {
        $statePath = $this->getStateFilePath($outputFile);
        if (file_exists($statePath)) {
            unlink($statePath);
        }
        $this->state = null;
    }

    private function getStateFilePath(string $outputFile): string
    {
        return $outputFile.'.state.json';
    }

    private function readStateFile(string $outputFile): ?array
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

    private function writeState(string $outputFile): void
    {
        $this->state['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        file_put_contents(
            $this->getStateFilePath($outputFile),
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @return array<int, true>
     */
    private function loadProcessedPartnerNumbers(string $outputFile): array
    {
        if (!file_exists($outputFile)) {
            return [];
        }

        $partnerMap = $this->csvStorage->readAsPartnerMap($outputFile);

        return array_fill_keys(array_keys($partnerMap), true);
    }
}
