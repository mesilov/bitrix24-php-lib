<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Services\Scraper;

use League\Csv\Reader;

class ScrapeStateManager
{
    private ?array $state = null;

    /**
     * @return null|array{lastPage: int, startPage: int, processedNumbers: array<int, true>, outputFile: string, zone: string}
     */
    public function resume(string $outputDir, string $zone): ?array
    {
        $state = $this->readStateFile($outputDir);
        if (null === $state) {
            return null;
        }

        if (rtrim($outputDir, '/') !== $state['output_dir']) {
            throw new \RuntimeException(sprintf(
                'output-dir не совпадает: запрошен "%s", ожидается "%s"',
                rtrim($outputDir, '/'),
                $state['output_dir'],
            ));
        }

        if ($zone !== $state['zone']) {
            throw new \RuntimeException(sprintf(
                'zone не совпадает: запрошена "%s", ожидается "%s"',
                $zone,
                $state['zone'],
            ));
        }

        $outputFile = $state['output_file'];
        $processedNumbers = $this->loadProcessedPartnerNumbers($outputFile);

        return [
            'lastPage' => $state['total_pages'],
            'startPage' => $state['last_completed_page'] + 1,
            'processedNumbers' => $processedNumbers,
            'outputFile' => $outputFile,
            'zone' => $state['zone'],
        ];
    }

    public function initState(string $outputDir, string $outputFile, string $baseUrl, int $lastPage, string $zone): void
    {
        $statePath = $this->getStateFilePath($outputDir);
        if (file_exists($statePath)) {
            unlink($statePath);
        }

        $this->state = [
            'mode' => 'full_scrape',
            'base_url' => $baseUrl,
            'total_pages' => $lastPage,
            'last_completed_page' => 0,
            'output_dir' => rtrim($outputDir, '/'),
            'output_file' => $outputFile,
            'zone' => $zone,
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updated_at' => '',
        ];
        $this->writeState($outputDir);
    }

    public function updateProgress(string $outputDir, int $completedPage): void
    {
        if (null === $this->state) {
            return;
        }

        $this->state['last_completed_page'] = $completedPage;
        $this->writeState($outputDir);
    }

    public function complete(string $outputDir): void
    {
        $statePath = $this->getStateFilePath($outputDir);
        if (file_exists($statePath)) {
            unlink($statePath);
        }

        $this->state = null;
    }

    private function getStateFilePath(string $outputDir): string
    {
        return rtrim($outputDir, '/').'/state.json';
    }

    private function readStateFile(string $outputDir): ?array
    {
        $statePath = $this->getStateFilePath($outputDir);
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

    private function writeState(string $outputDir): void
    {
        $this->state['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $result = file_put_contents(
            $this->getStateFilePath($outputDir),
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if (false === $result) {
            throw new \RuntimeException(sprintf('Не удалось записать state-файл: %s', $this->getStateFilePath($outputDir)));
        }
    }

    /**
     * @return array<int, true>
     */
    private function loadProcessedPartnerNumbers(string $outputFile): array
    {
        if (!file_exists($outputFile)) {
            return [];
        }

        $reader = Reader::from($outputFile);
        $reader->setHeaderOffset(0);

        $numbers = [];
        foreach ($reader->getRecords() as $record) {
            $number = (int) ($record['bitrix24_partner_number'] ?? 0);
            if ($number > 0) {
                $numbers[$number] = true;
            }
        }

        return $numbers;
    }
}
