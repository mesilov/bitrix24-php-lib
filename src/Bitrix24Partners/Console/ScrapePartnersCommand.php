<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\ScrapeResult;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\ScrapeWorkflow;
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
    private const string DEFAULT_BASE_URL = 'https://www.bitrix24.ru/partners/country__19/';

    private const string DEFAULT_OUTPUT_FILE = 'partners.csv';

    private const int DEFAULT_PAGE_DELAY = 2;

    private const int DEFAULT_PARTNER_DELAY = 2;

    private SymfonyStyle $io;

    private OutputInterface $output;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScrapeWorkflow $workflow,
    ) {
        parent::__construct();
    }

    #[\Override]
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

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;

        $baseUrl = $input->getOption('base-url');
        $outputFile = $input->getOption('output-file');
        $pageDelay = (int) $input->getOption('page-delay');
        $partnerDelay = (int) $input->getOption('partner-delay');
        $insecure = (bool) $input->getOption('insecure');
        $resume = (bool) $input->getOption('resume');
        $fullRefresh = (bool) $input->getOption('full-refresh');

        if ($this->io->isVerbose()) {
            $this->io->text(sprintf('Base URL: %s', $baseUrl));
            $this->io->text(sprintf('Output file: %s', $outputFile));
        }

        $baseDomain = $this->extractBaseDomain($baseUrl);

        try {
            return $this->executeFullScrape($baseUrl, $outputFile, $pageDelay, $partnerDelay, $insecure, $resume, $fullRefresh, $baseDomain);
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $this->io->error('Ошибка: '.$throwable->getMessage());

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
        string $baseDomain,
    ): int {
        if (!$resume && !$fullRefresh && file_exists($outputFile)) {
            $this->io->error(sprintf('Файл %s уже существует. Используйте --full-refresh для перезаписи.', $outputFile));

            return Command::FAILURE;
        }

        $onProgress = $this->io->isVerbose()
            ? fn (string $message) => $this->io->text($message)
            : null;

        $context = $this->workflow->resolveStartContext($baseUrl, $outputFile, $insecure, $resume, $onProgress);
        if (null === $context) {
            $this->io->error('State-файл не найден. Запустите без --resume.');

            return Command::FAILURE;
        }

        $startPage = $context['startPage'];
        $lastPage = $context['lastPage'];
        $processedNumbers = $context['processedNumbers'];
        $partnersPerPage = $context['partnersPerPage'];

        if ($resume) {
            if ($this->io->isVerbose()) {
                $this->io->note(sprintf(
                    'Resume: продолжаем со страницы %d из %d (уже обработано: %d)',
                    $startPage,
                    $lastPage,
                    count($processedNumbers)
                ));
            }
        } else {
            if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
                $this->io->section('Определение количества страниц...');
                $this->io->success(sprintf(
                    'Найдено страниц: %d | Партнёров на странице: %d (≈%d партнёров)',
                    $lastPage,
                    $partnersPerPage,
                    $lastPage * $partnersPerPage
                ));
                $this->io->section('Парсинг партнёров...');
            }
        }

        $progressBar = $this->createProgressBar($lastPage * $partnersPerPage, count($processedNumbers));

        $result = $this->workflow->run(
            $startPage,
            $lastPage,
            $baseUrl,
            $baseDomain,
            $insecure,
            $pageDelay,
            $partnerDelay,
            $outputFile,
            $resume,
            $processedNumbers,
            onPageStart: fn (int $page) => $progressBar?->setMessage((string) $page, 'page'),
            onPartnerStart: fn (int $partnerNumber) => $progressBar?->setMessage((string) $partnerNumber, 'partner'),
            onPartnerAdvance: fn () => $progressBar?->advance(),
        );

        return $this->finishScrape($outputFile, $progressBar, $result);
    }

    private function createProgressBar(int $total, int $alreadyProcessed): ?ProgressBar
    {
        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_NORMAL) {
            return null;
        }

        $progressBar = new ProgressBar($this->output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Стр: %page% | ID: %partner%');
        $progressBar->setMessage('', 'page');
        $progressBar->setMessage('', 'partner');
        $progressBar->advance($alreadyProcessed);

        return $progressBar;
    }

    private function finishScrape(string $outputFile, ?ProgressBar $progressBar, ScrapeResult $result): int
    {
        $progressBar?->finish();
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->newLine(2);
        }

        if ($result->banDetected) {
            $this->io->warning(sprintf(
                'Парсинг прерван. Обработано партнёров: %d | Пустых страниц: %d из %d. Возможно, доступ заблокирован — увеличьте задержки (--partner-delay, --page-delay) и попробуйте позже.',
                $result->totalProcessed,
                $result->totalEmptyPages,
                $result->totalPagesProcessed
            ));

            return Command::FAILURE;
        }

        $this->workflow->complete($outputFile);

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->success(sprintf('Парсинг завершён. Обработано партнёров: %d', $result->totalProcessed));
        }

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
