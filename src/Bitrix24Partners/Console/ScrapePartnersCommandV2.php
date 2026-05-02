<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerHtmlParser;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerPageScraper;
use League\Csv\Reader;
use League\Csv\Writer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'partners:scrape-v2',
    description: 'Парсит партнеров Bitrix24 и сохраняет данные в CSV'
)]
class ScrapePartnersCommandV2 extends Command
{
    private const DEFAULT_BASE_URL = 'https://www.bitrix24.kz/partners/country__22/';
    private const DEFAULT_OUTPUT_FILE = 'partners.csv';
    private const DEFAULT_PAGE_DELAY = 2;
    private const DEFAULT_PARTNER_DELAY = 2;
    private const CSV_HEADERS = [
        'bitrix24_partner_number',
        'title',
        'site',
        'phone',
        'email',
        'logo_url',
        'detail_page_url',
        'scraped_at',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PartnerPageScraper $scraper,
        private readonly PartnerHtmlParser $parser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'URL страницы партнёров', self::DEFAULT_BASE_URL)
            ->addOption('output-file', null, InputOption::VALUE_REQUIRED, 'Путь к выходному CSV файлу', self::DEFAULT_OUTPUT_FILE)
            ->addOption('page-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между страницами (сек)', (string) self::DEFAULT_PAGE_DELAY)
            ->addOption('partner-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между партнёрами (сек)', (string) self::DEFAULT_PARTNER_DELAY)
            ->addOption('insecure', null, InputOption::VALUE_NONE, 'Отключить проверку SSL (для dev)')
            ->addOption('resume', null, InputOption::VALUE_NONE, 'Продолжить с места обрыва (из state-файла)')
            ->addOption('full-refresh', null, InputOption::VALUE_NONE, 'Перечитать всех с сайта → перезаписать CSV')
            ->addOption('partner-ids', null, InputOption::VALUE_REQUIRED, 'Обновить конкретных партнёров (через запятую)', '')
            ->addOption('partner-ids-from-file', null, InputOption::VALUE_REQUIRED, 'Файл с номерами партнёров для обновления', '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $baseUrl = $input->getOption('base-url');
        $outputFile = $input->getOption('output-file');
        $pageDelay = (int) $input->getOption('page-delay');
        $partnerDelay = (int) $input->getOption('partner-delay');
        $insecure = (bool) $input->getOption('insecure');
        $resume = (bool) $input->getOption('resume');
        $fullRefresh = (bool) $input->getOption('full-refresh');
        $partnerIds = $input->getOption('partner-ids');
        $partnerIdsFromFile = $input->getOption('partner-ids-from-file');

        $io->info('Начало парсинга партнеров Bitrix24...');
        $io->writeln(sprintf('Base URL: %s', $baseUrl));
        $io->writeln(sprintf('Output file: %s', $outputFile));

        $baseDomain = $this->extractBaseDomain($baseUrl);

        try {
            if ('' !== $partnerIds) {
                return $this->executePartnerUpdate(
                    explode(',', $partnerIds),
                    $outputFile,
                    $partnerDelay,
                    $insecure,
                    $io,
                    $baseDomain
                );
            }

            if ('' !== $partnerIdsFromFile) {
                return $this->executePartnerUpdateFromFile(
                    $partnerIdsFromFile,
                    $outputFile,
                    $partnerDelay,
                    $insecure,
                    $io,
                    $baseDomain
                );
            }

            return $this->executeFullScrape(
                $baseUrl,
                $outputFile,
                $pageDelay,
                $partnerDelay,
                $insecure,
                $resume,
                $fullRefresh,
                $output,
                $io
            );
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка: '.$e->getMessage());
            $io->error('Ошибка: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function executeFullScrape(
        string $baseUrl,
        string $outputFile,
        int $pageDelay,
        int $partnerDelay,
        bool $insecure,
        bool $resume,
        bool $fullRefresh,
        OutputInterface $output,
        SymfonyStyle $io,
    ): int {
        $baseDomain = $this->extractBaseDomain($baseUrl);
        if (!$resume && !$fullRefresh && file_exists($outputFile)) {
            $io->error(sprintf('Файл %s уже существует. Используйте --full-refresh для перезаписи.', $outputFile));

            return Command::FAILURE;
        }

        $startPage = 1;
        $lastPage = 0;
        $processedNumbers = [];
        $partnersPerPage = 12;

        if ($resume) {
            $state = $this->readStateFile($outputFile);
            if (null === $state) {
                $io->error('State-файл не найден. Запустите без --resume.');

                return Command::FAILURE;
            }

            $lastPage = $state['total_pages'];
            $startPage = $state['last_completed_page'] + 1;
            $processedNumbers = $this->loadProcessedPartnerNumbers($outputFile);

            $io->note(sprintf(
                'Resume: продолжаем со страницы %d из %d (уже обработано: %d)',
                $startPage,
                $lastPage,
                count($processedNumbers)
            ));
        } else {
            $io->section('Определение количества страниц...');
            $lastPage = $this->scraper->findLastPage($baseUrl, $insecure, $io);

            $firstPageHtml = $this->scraper->fetchPageHtml(1, $baseUrl, $insecure);
            if (null !== $firstPageHtml) {
                $firstPagePartners = $this->parser->parsePartnerListPage($firstPageHtml);
                if (count($firstPagePartners) > 0) {
                    $partnersPerPage = count($firstPagePartners);
                }
            }

            $io->success(sprintf('Найдено страниц: %d | Партнёров на странице: %d (≈%d партнёров)', $lastPage, $partnersPerPage, $lastPage * $partnersPerPage));
        }

        $estimatedPartners = $lastPage * $partnersPerPage;

        $io->section('Парсинг партнёров...');
        $progressBar = new ProgressBar($output, $estimatedPartners);
        $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% | Страница: %page% | Партнёр: %partner%');
        $progressBar->setMessage('', 'page');
        $progressBar->setMessage('', 'partner');
        $progressBar->advance(count($processedNumbers));

        if ($resume) {
            $csvWriter = $this->createCsvWriterForResume($outputFile);
        } else {
            $csvWriter = $this->createCsvWriter($outputFile);
        }

        $totalProcessed = count($processedNumbers);
        $state = [
            'mode' => 'full_scrape',
            'base_url' => $baseUrl,
            'total_pages' => $lastPage,
            'last_completed_page' => 0,
            'output_file' => $outputFile,
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updated_at' => '',
        ];

        $consecutiveEmptyPages = 0;
        $totalEmptyPages = 0;
        $totalPagesProcessed = 0;
        $banDetected = false;

        for ($page = $startPage; $page <= $lastPage; ++$page) {
            $progressBar->setMessage((string) $page, 'page');

            try {
                $html = $this->scraper->fetchPageHtml($page, $baseUrl, $insecure);
                if (null === $html) {
                    $this->logger->warning(sprintf('Страница %d пустая, пропускаем', $page));
                    ++$consecutiveEmptyPages;
                    ++$totalEmptyPages;
                    ++$totalPagesProcessed;

                    if ($consecutiveEmptyPages >= 10) {
                        $banDetected = true;
                        $this->logger->error(sprintf(
                            'Обнаружена блокировка: %d страниц подряд возвращают пустой ответ (страница %d). Рекомендуется увеличить задержки.',
                            $consecutiveEmptyPages,
                            $page
                        ));
                        $io->error(sprintf(
                            'Обнаружена блокировка: %d страниц подряд возвращают пустой ответ. Скорее всего, доступ заблокирован. Попробуйте увеличить --partner-delay и --page-delay до 2-3 секунд.',
                            $consecutiveEmptyPages
                        ));

                        break;
                    }

                    continue;
                }

                $partners = $this->parser->parsePartnerListPage($html);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Ошибка при обработке страницы %d: %s', $page, $e->getMessage()));

                continue;
            }

            ++$totalPagesProcessed;
            if (count($partners) > 0) {
                $consecutiveEmptyPages = 0;
            } else {
                ++$consecutiveEmptyPages;
                ++$totalEmptyPages;

                if ($consecutiveEmptyPages >= 10) {
                    $banDetected = true;
                    $this->logger->error(sprintf(
                        'Обнаружена блокировка: %d страниц подряд без партнёров (страница %d).',
                        $consecutiveEmptyPages,
                        $page
                    ));
                    $io->error(sprintf(
                        'Обнаружена блокировка: %d страниц подряд без партнёров. Скорее всего, доступ заблокирован. Попробуйте увеличить --partner-delay и --page-delay до 2-3 секунд.',
                        $consecutiveEmptyPages
                    ));

                    break;
                }
            }

            foreach ($partners as $partner) {
                $partnerNumber = $partner['partner_number'];
                $progressBar->setMessage((string) $partnerNumber, 'partner');

                if (isset($processedNumbers[$partnerNumber])) {
                    $progressBar->advance();

                    continue;
                }

                try {
                    $detailHtml = $this->scraper->fetchPartnerDetailHtml($partner['detail_page_url'], $insecure, $baseDomain);

                    $detailData = [
                        'phone' => '',
                        'email' => '',
                        'logo_url' => '',
                        'site' => '',
                    ];
                    if (null !== $detailHtml) {
                        $detailData = $this->parser->parsePartnerDetailPage($detailHtml);
                    }

                    $this->writePartnerToCsv($csvWriter, array_merge($partner, $detailData));

                    $processedNumbers[$partnerNumber] = true;
                    ++$totalProcessed;
                } catch (\Throwable $e) {
                    $this->logger->warning(sprintf(
                        'Ошибка при обработке партнёра #%d: %s',
                        $partnerNumber,
                        $e->getMessage()
                    ));
                }

                $progressBar->advance();
                $state['last_completed_page'] = $page;
                $this->writeStateFile($outputFile, $state);
                sleep($partnerDelay);
            }

            $state['last_completed_page'] = $page;
            $this->writeStateFile($outputFile, $state);
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

        $progressBar->finish();
        $io->newLine(2);

        if ($banDetected) {
            $io->warning(sprintf(
                'Парсинг прерван. Обработано партнёров: %d | Пустых страниц: %d из %d. Возможно, доступ заблокирован — увеличьте задержки (--partner-delay, --page-delay) и попробуйте позже.',
                $totalProcessed,
                $totalEmptyPages,
                $totalPagesProcessed
            ));

            return Command::FAILURE;
        }

        $this->deleteStateFile($outputFile);
        $io->success(sprintf('Парсинг завершён. Обработано партнёров: %d', $totalProcessed));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, string> $partnerIdList
     */
    private function executePartnerUpdate(
        array $partnerIdList,
        string $outputFile,
        int $partnerDelay,
        bool $insecure,
        SymfonyStyle $io,
        string $baseDomain,
    ): int {
        if (!file_exists($outputFile)) {
            $io->error(sprintf('CSV файл %s не найден. Сначала выполните полную выгрузку.', $outputFile));

            return Command::FAILURE;
        }

        $partnerIds = array_map('intval', array_filter(array_map('trim', $partnerIdList)));
        if (0 === count($partnerIds)) {
            $io->error('Список ID партнёров пуст.');

            return Command::FAILURE;
        }

        $io->text(sprintf('Обновление %d партнёров...', count($partnerIds)));

        $reader = Reader::from($outputFile);
        $reader->setHeaderOffset(0);

        $records = [];
        foreach ($reader->getRecords() as $record) {
            $number = (int) ($record['bitrix24_partner_number'] ?? 0);
            if ($number > 0) {
                $records[$number] = $record;
            }
        }

        $updated = 0;
        $errors = 0;

        foreach ($partnerIds as $partnerId) {
            if (!isset($records[$partnerId])) {
                $io->warning(sprintf('Партнёр #%d не найден в CSV, пропускаем', $partnerId));
                ++$errors;

                continue;
            }

            $detailPageUrl = $records[$partnerId]['detail_page_url'] ?? '';
            if ('' === $detailPageUrl) {
                $io->warning(sprintf('Партнёр #%d: нет detail_page_url, пропускаем', $partnerId));
                ++$errors;

                continue;
            }

            $io->text(sprintf('Обновление партнёра #%d...', $partnerId));

            try {
                $detailHtml = $this->scraper->fetchPartnerDetailHtml($detailPageUrl, $insecure, $baseDomain);
                if (null === $detailHtml) {
                    $io->warning(sprintf('Партнёр #%d: не удалось загрузить детальную страницу', $partnerId));
                    ++$errors;

                    continue;
                }

                $detailData = $this->parser->parsePartnerDetailPage($detailHtml);

                $records[$partnerId]['phone'] = $detailData['phone'];
                $records[$partnerId]['email'] = $detailData['email'];
                $records[$partnerId]['logo_url'] = $detailData['logo_url'];
                $records[$partnerId]['site'] = $detailData['site'];
                $records[$partnerId]['scraped_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

                ++$updated;
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка обновления партнёра #%d: %s', $partnerId, $e->getMessage()));
                ++$errors;
            }

            sleep($partnerDelay);
        }

        $writer = Writer::from($outputFile, 'w+');
        $writer->insertOne(self::CSV_HEADERS);
        foreach ($records as $record) {
            $writer->insertOne(array_values($record));
        }

        $io->newLine();
        $io->success(sprintf('Обновлено: %d, ошибок: %d', $updated, $errors));

        return Command::SUCCESS;
    }

    private function executePartnerUpdateFromFile(
        string $idsFilePath,
        string $outputFile,
        int $partnerDelay,
        bool $insecure,
        SymfonyStyle $io,
        string $baseDomain,
    ): int {
        if (!file_exists($idsFilePath)) {
            $io->error(sprintf('Файл с ID партнёров не найден: %s', $idsFilePath));

            return Command::FAILURE;
        }

        $reader = Reader::from($idsFilePath);
        $partnerIds = [];
        foreach ($reader->getRecords() as $record) {
            $id = (int) trim(array_values($record)[0]);
            if ($id > 0) {
                $partnerIds[] = (string) $id;
            }
        }

        if (0 === count($partnerIds)) {
            $io->error('Файл не содержит ID партнёров.');

            return Command::FAILURE;
        }

        return $this->executePartnerUpdate($partnerIds, $outputFile, $partnerDelay, $insecure, $io, $baseDomain);
    }

    private function createCsvWriter(string $outputFile): Writer
    {
        $writer = Writer::from($outputFile, 'w+');
        $writer->insertOne(self::CSV_HEADERS);

        return $writer;
    }

    private function createCsvWriterForResume(string $outputFile): Writer
    {
        return Writer::from($outputFile, 'a+');
    }

    /**
     * @param array{partner_number: int, title: string, detail_page_url: string, phone: string, email: string, logo_url: string, site: string} $partner
     */
    private function writePartnerToCsv(Writer $writer, array $partner): void
    {
        $writer->insertOne([
            $partner['partner_number'],
            $partner['title'],
            $partner['site'],
            $partner['phone'],
            $partner['email'],
            $partner['logo_url'],
            $partner['detail_page_url'],
            (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function getStateFilePath(string $outputFile): string
    {
        return $outputFile.'.state.json';
    }

    /**
     * @return null|array{mode: string, base_url: string, total_pages: int, last_completed_page: int, output_file: string, started_at: string, updated_at: string}
     */
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

    /**
     * @param array{mode: string, base_url: string, total_pages: int, last_completed_page: int, output_file: string, started_at: string, updated_at: string} $state
     */
    private function writeStateFile(string $outputFile, array $state): void
    {
        $state['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        file_put_contents(
            $this->getStateFilePath($outputFile),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function deleteStateFile(string $outputFile): void
    {
        $statePath = $this->getStateFilePath($outputFile);
        if (file_exists($statePath)) {
            unlink($statePath);
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

    private function extractBaseDomain(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'www.bitrix24.kz';

        return $scheme . '://' . $host;
    }
}
