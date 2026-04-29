<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use League\Csv\Reader;
use League\Csv\Writer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
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
    private const DEFAULT_PAGE_DELAY = 3;
    private const DEFAULT_PARTNER_DELAY = 2;
    private const HTTP_TIMEOUT = 10;
    private const PARTNERS_PER_PAGE = 16;
    private const BINARY_SEARCH_STEP = 100;
    private const CSV_HEADERS = [
        'bitrix24_partner_number',
        'title',
        'site',
        'phone',
        'email',
        'logo_url',
        'detail_page_url',
        'open_line_id',
        'external_id',
        'scraped_at',
    ];

    private ?ClientInterface $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
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
            ->addOption('partner-ids-from-file', null, InputOption::VALUE_REQUIRED, 'Файл с номерами партнёров для обновления', '');
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

        try {
            if ($partnerIds !== '') {
                return $this->executePartnerUpdate(
                    explode(',', $partnerIds),
                    $outputFile,
                    $partnerDelay,
                    $insecure,
                    $io
                );
            }

            if ($partnerIdsFromFile !== '') {
                return $this->executePartnerUpdateFromFile(
                    $partnerIdsFromFile,
                    $outputFile,
                    $partnerDelay,
                    $insecure,
                    $io
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
            $this->logger->error('Ошибка: ' . $e->getMessage());
            $io->error('Ошибка: ' . $e->getMessage());

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
        if (!$resume && !$fullRefresh && file_exists($outputFile)) {
            $io->error(sprintf('Файл %s уже существует. Используйте --full-refresh для перезаписи.', $outputFile));

            return Command::FAILURE;
        }

        $state = null;
        $startPage = 1;
        $lastPage = 0;
        $processedNumbers = [];

        if ($resume) {
            $state = $this->readStateFile($outputFile);
            if ($state === null) {
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
            $lastPage = $this->findLastPage($baseUrl, $insecure, $io);

            $io->success(sprintf('Найдено страниц: %d (≈%d партнёров)', $lastPage, $lastPage * self::PARTNERS_PER_PAGE));
        }

        $estimatedPartners = $lastPage * self::PARTNERS_PER_PAGE;

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

        for ($page = $startPage; $page <= $lastPage; ++$page) {
            $progressBar->setMessage((string) $page, 'page');

            try {
                $html = $this->fetchPageHtml($page, $baseUrl, $insecure);
                if ($html === null) {
                    $this->logger->warning(sprintf('Страница %d пустая, пропускаем', $page));
                    continue;
                }

                $partners = $this->parsePartnerListPage($html);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Ошибка при обработке страницы %d: %s', $page, $e->getMessage()));
                continue;
            }

            foreach ($partners as $partner) {
                $partnerNumber = $partner['partner_number'];
                $progressBar->setMessage((string) $partnerNumber, 'partner');

                if (isset($processedNumbers[$partnerNumber])) {
                    $progressBar->advance();
                    continue;
                }

                try {
                    $detailHtml = $this->fetchPartnerDetailHtml($partner['detail_page_url'], $insecure);

                    $detailData = [
                        'phone' => '',
                        'email' => '',
                        'logo_url' => '',
                        'site' => '',
                    ];
                    if ($detailHtml !== null) {
                        $detailData = $this->parsePartnerDetailPage($detailHtml);
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

        $this->deleteStateFile($outputFile);
        $progressBar->finish();
        $io->newLine(2);
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
    ): int {
        if (!file_exists($outputFile)) {
            $io->error(sprintf('CSV файл %s не найден. Сначала выполните полную выгрузку.', $outputFile));

            return Command::FAILURE;
        }

        $partnerIds = array_map('intval', array_filter(array_map('trim', $partnerIdList)));
        if (count($partnerIds) === 0) {
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
            if ($detailPageUrl === '') {
                $io->warning(sprintf('Партнёр #%d: нет detail_page_url, пропускаем', $partnerId));
                ++$errors;
                continue;
            }

            $io->text(sprintf('Обновление партнёра #%d...', $partnerId));

            try {
                $detailHtml = $this->fetchPartnerDetailHtml($detailPageUrl, $insecure);
                if ($detailHtml === null) {
                    $io->warning(sprintf('Партнёр #%d: не удалось загрузить детальную страницу', $partnerId));
                    ++$errors;
                    continue;
                }

                $detailData = $this->parsePartnerDetailPage($detailHtml);

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

        if (count($partnerIds) === 0) {
            $io->error('Файл не содержит ID партнёров.');

            return Command::FAILURE;
        }

        return $this->executePartnerUpdate($partnerIds, $outputFile, $partnerDelay, $insecure, $io);
    }

    private function findLastPage(string $baseUrl, bool $insecure, SymfonyStyle $io): int
    {
        $io->text('Проверяем страницу 1...');
        if (!$this->hasPartnersOnPage(1, $baseUrl, $insecure)) {
            throw new \RuntimeException('Страница 1 не существует или не содержит партнёров. Проверьте URL и доступность сайта.');
        }
        $io->text('Страница 1 существует');

        $low = 1;
        $high = self::BINARY_SEARCH_STEP;

        while ($this->hasPartnersOnPage($high, $baseUrl, $insecure)) {
            $io->text(sprintf('Страница %d существует, проверяем %d...', $high, $high + self::BINARY_SEARCH_STEP));
            $low = $high;
            $high += self::BINARY_SEARCH_STEP;
            sleep(1);
        }

        $io->text(sprintf('Страница %d не существует, бинарный поиск между %d и %d...', $high, $low, $high));

        while ($low < $high - 1) {
            $mid = (int) ceil(($low + $high) / 2);

            if ($this->hasPartnersOnPage($mid, $baseUrl, $insecure)) {
                $io->text(sprintf('Страница %d существует', $mid));
                $low = $mid;
            } else {
                $io->text(sprintf('Страница %d не существует', $mid));
                $high = $mid;
            }
            sleep(1);
        }

        return $low;
    }

    private function hasPartnersOnPage(int $pageNumber, string $baseUrl, bool $insecure): bool
    {
        $html = $this->fetchPageHtml($pageNumber, $baseUrl, $insecure);
        if ($html === null) {
            return false;
        }

        $crawler = new Crawler($html);

        return $crawler->filter('div.bp-partner-list-item-cnr.js-partners-list-item')->count() > 0;
    }

    private function fetchPageHtml(int $pageNumber, string $baseUrl, bool $insecure): ?string
    {
        $request = $this->getRequestFactory()->createRequest('POST', $baseUrl)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Referer', 'https://www.bitrix24.kz/partners/');

        $body = $this->getStreamFactory()->createStream(
            http_build_query(['ajax' => 'Y', 'page_n' => $pageNumber])
        );
        $request = $request->withBody($body);

        $response = $this->getHttpClient($insecure)->sendRequest($request);
        $content = $response->getBody()->getContents();

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['html'])) {
            return null;
        }

        return $data['html'];
    }

    /**
     * @return array<int, array{partner_number: int, title: string, detail_page_url: string, phone: string}>
     */
    private function parsePartnerListPage(string $html): array
    {
        $crawler = new Crawler($html);
        $partners = [];

        $crawler->filter('div.bp-partner-list-item-cnr.js-partners-list-item')->each(
            function (Crawler $node) use (&$partners) {
                $partnerNumber = (int) $node->attr('data-partner-id');
                if ($partnerNumber === 0) {
                    return;
                }

                $title = '';
                $detailPageUrl = '';
                $nameLink = $node->filter('a.bp-partner-list-item-name')->first();
                if ($nameLink->count() > 0) {
                    $title = trim($nameLink->text());
                    $detailPageUrl = $nameLink->attr('href') ?? '';
                }

                $phone = '';
                $phoneNode = $node->filter('div.bp-partner-request-phone')->first();
                if ($phoneNode->count() > 0) {
                    $phone = trim($phoneNode->text());
                }

                $partners[$partnerNumber] = [
                    'partner_number' => $partnerNumber,
                    'title' => $title,
                    'detail_page_url' => $detailPageUrl,
                    'phone' => $phone,
                ];
            }
        );

        return $partners;
    }

    private function fetchPartnerDetailHtml(string $detailPageUrl, bool $insecure): ?string
    {
        if ($detailPageUrl === '') {
            return null;
        }

        $fullUrl = 'https://www.bitrix24.kz' . $detailPageUrl;

        try {
            $request = $this->getRequestFactory()->createRequest('GET', $fullUrl)
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeader('Referer', 'https://www.bitrix24.kz/partners/');

            $response = $this->getHttpClient($insecure)->sendRequest($request);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка при загрузке детальной страницы %s: %s', $fullUrl, $e->getMessage()));

            return null;
        }
    }

    /**
     * @return array{email: string, logo_url: string, site: string, phone: string}
     */
    private function parsePartnerDetailPage(string $html): array
    {
        $crawler = new Crawler($html);

        $phone = '';
        try {
            $contactsNode = $crawler->filter('div.bx-partner-detail-description-contacts-content')->first();
            if ($contactsNode->count() > 0) {
                $contactsNode->filter('p')->each(function (Crawler $p) use (&$phone) {
                    $b = $p->filter('b');
                    if ($b->count() > 0 && str_contains(trim($b->text()), 'Телефон')) {
                        $fullText = trim($p->text());
                        $label = trim($b->text());
                        $extracted = trim(str_replace($label, '', $fullText));
                        if ($extracted !== '') {
                            $phone = $extracted;
                        }
                    }
                });
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга phone: %s', $e->getMessage()));
        }

        $email = '';
        try {
            $contactsNode = $crawler->filter('div.bx-partner-detail-description-contacts-content')->first();
            if ($contactsNode->count() > 0) {
                $contactsNode->filter('a')->each(function (Crawler $a) use (&$email) {
                    $text = trim($a->text());
                    if (str_contains($text, '@')) {
                        $email = $text;
                    }
                });
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга email: %s', $e->getMessage()));
        }

        $logoUrl = '';
        try {
            $logoNode = $crawler->filter('img.bx-partner-detail-header-logo-img')->first();
            if ($logoNode->count() > 0) {
                $logoUrl = $logoNode->attr('src') ?? '';
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга logo_url: %s', $e->getMessage()));
        }

        $site = '';
        try {
            $siteNode = $crawler->filter('a.bx-partner-detail-header-info-link')->first();
            if ($siteNode->count() > 0) {
                $site = $siteNode->attr('href') ?? '';
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга site: %s', $e->getMessage()));
        }

        return [
            'phone' => $phone,
            'email' => $email,
            'logo_url' => $logoUrl,
            'site' => $site,
        ];
    }

    private function createCsvWriter(string $outputFile): Writer
    {
        $writer = Writer::from($outputFile, 'w+');
        $writer->insertOne(self::CSV_HEADERS);

        return $writer;
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
            '',
            '',
            (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function getStateFilePath(string $outputFile): string
    {
        return $outputFile . '.state.json';
    }

    /**
     * @return array{mode: string, base_url: string, total_pages: int, last_completed_page: int, output_file: string, started_at: string, updated_at: string}|null
     */
    private function readStateFile(string $outputFile): ?array
    {
        $statePath = $this->getStateFilePath($outputFile);
        if (!file_exists($statePath)) {
            return null;
        }

        $content = file_get_contents($statePath);
        if ($content === false) {
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

    private function createCsvWriterForResume(string $outputFile): Writer
    {
        return Writer::from($outputFile, 'a+');
    }

    private function getHttpClient(bool $insecure = false): ClientInterface
    {
        if ($insecure && $this->httpClient === null) {
            $symfonyClient = \Symfony\Component\HttpClient\HttpClient::create([
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => self::HTTP_TIMEOUT,
            ]);
            $this->httpClient = new \Symfony\Component\HttpClient\Psr18Client($symfonyClient);

            return $this->httpClient;
        }

        if ($this->httpClient === null) {
            $this->httpClient = Psr18ClientDiscovery::find();
        }

        return $this->httpClient;
    }

    private function getRequestFactory(): RequestFactoryInterface
    {
        if ($this->requestFactory === null) {
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        }

        return $this->requestFactory;
    }

    private function getStreamFactory(): StreamFactoryInterface
    {
        if ($this->streamFactory === null) {
            $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        }

        return $this->streamFactory;
    }
}
