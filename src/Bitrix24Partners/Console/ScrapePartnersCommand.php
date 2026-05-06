<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerCsvStorage;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerHtmlParser;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerPageScraper;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\ScrapeStateManager;
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
    name: 'partners:scrape',
    description: 'Парсит партнеров Bitrix24 и сохраняет данные в CSV'
)]
class ScrapePartnersCommand extends Command
{
    private const DEFAULT_BASE_URL = 'https://www.bitrix24.ru/partners/country__19/';
    private const DEFAULT_OUTPUT_FILE = 'partners.csv';
    private const DEFAULT_PAGE_DELAY = 2;
    private const DEFAULT_PARTNER_DELAY = 2;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PartnerPageScraper $scraper,
        private readonly PartnerHtmlParser $parser,
        private readonly PartnerCsvStorage $csvStorage,
        private readonly ScrapeStateManager $stateManager,
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

        $io->info('Начало парсинга партнеров Bitrix24...');
        $io->writeln(sprintf('Base URL: %s', $baseUrl));
        $io->writeln(sprintf('Output file: %s', $outputFile));

        $baseDomain = $this->extractBaseDomain($baseUrl);

        try {
            return $this->executeFullScrape(
                $baseUrl,
                $outputFile,
                $pageDelay,
                $partnerDelay,
                $insecure,
                $resume,
                $fullRefresh,
                $output,
                $io,
                $baseDomain
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
        string $baseDomain,
    ): int {
        if (!$resume && !$fullRefresh && file_exists($outputFile)) {
            $io->error(sprintf('Файл %s уже существует. Используйте --full-refresh для перезаписи.', $outputFile));

            return Command::FAILURE;
        }

        $startPage = 1;
        $lastPage = 0;
        $processedNumbers = [];
        $partnersPerPage = 12;

        if ($resume) {
            $state = $this->stateManager->read($outputFile);
            if (null === $state) {
                $io->error('State-файл не найден. Запустите без --resume.');

                return Command::FAILURE;
            }

            $lastPage = $state['total_pages'];
            $startPage = $state['last_completed_page'] + 1;
            $processedNumbers = $this->stateManager->loadProcessedPartnerNumbers($outputFile);

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
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Стр: %page% | ID: %partner%');
        $progressBar->setMessage('', 'page');
        $progressBar->setMessage('', 'partner');
        $progressBar->advance(count($processedNumbers));

        if ($resume) {
            $csvWriter = $this->csvStorage->createWriterForResume($outputFile);
        } else {
            $csvWriter = $this->csvStorage->createWriter($outputFile);
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

            $html = null;
            $partners = [];

            try {
                $html = $this->scraper->fetchPageHtml($page, $baseUrl, $insecure);
                if (null !== $html) {
                    $partners = $this->parser->parsePartnerListPage($html);
                } else {
                    $this->logger->warning(sprintf('Страница %d пустая, пропускаем', $page));
                }
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Ошибка при обработке страницы %d: %s', $page, $e->getMessage()));
            }

            ++$totalPagesProcessed;

            if (null === $html || 0 === count($partners)) {
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
                $io->error(sprintf(
                    'Обнаружена блокировка: %d страниц подряд без данных. Скорее всего, доступ заблокирован. Попробуйте увеличить --partner-delay и --page-delay до 2-3 секунд.',
                    $consecutiveEmptyPages
                ));

                break;
            }

            if (null === $html) {
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

                    $this->csvStorage->writePartner($csvWriter, array_merge($partner, $detailData, ['base_domain' => $baseDomain]));

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
                $this->stateManager->write($outputFile, $state);
                sleep($partnerDelay);
            }

            $state['last_completed_page'] = $page;
            $this->stateManager->write($outputFile, $state);
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

        $this->stateManager->delete($outputFile);
        $io->success(sprintf('Парсинг завершён. Обработано партнёров: %d', $totalProcessed));

        return Command::SUCCESS;
    }

    private function extractBaseDomain(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];

        return $scheme.'://'.$host;
    }
}
