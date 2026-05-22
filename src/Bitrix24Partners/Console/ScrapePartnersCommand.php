<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeConfig;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeResult;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeWorkflow;
use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
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
            ->addOption('zone', null, InputOption::VALUE_REQUIRED, 'Зона Bitrix24 (ru, kz)', 'ru')
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

        $config = $this->resolveConfig($input);
        if (null === $config) {
            return Command::FAILURE;
        }

        if ($this->io->isVerbose()) {
            $this->io->text(sprintf('Zone: %s', $config->zone->value));
            $this->io->text(sprintf('Base URL: %s', $config->baseUrl));
            $this->io->text(sprintf('Output file: %s', $config->outputFile));
            $this->io->text(sprintf('Page delay: %d sec', $config->pageDelay));
            $this->io->text(sprintf('Partner delay: %d sec', $config->partnerDelay));
            $this->io->text(sprintf('Insecure: %s', $config->insecure ? 'yes' : 'no'));
            $this->io->text(sprintf('Resume: %s', $config->resume ? 'yes' : 'no'));
            $this->io->text(sprintf('Full refresh: %s', $config->fullRefresh ? 'yes' : 'no'));
        }

        try {
            return $this->executeFullScrape($config);
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $this->io->error('Ошибка: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }

    private function resolveConfig(InputInterface $input): ?ScrapeConfig
    {
        try {
            $zone = Bitrix24Zone::from($input->getOption('zone'));
        } catch (\ValueError) {
            $this->io->error(sprintf(
                'Invalid zone "%s". Allowed values: %s',
                $input->getOption('zone'),
                implode(', ', array_map(static fn (Bitrix24Zone $z) => $z->value, Bitrix24Zone::cases())),
            ));

            return null;
        }

        $pageDelay = (int) $input->getOption('page-delay');
        if ($pageDelay <= 0) {
            $this->io->error('page-delay must be greater than 0');

            return null;
        }

        $partnerDelay = (int) $input->getOption('partner-delay');
        if ($partnerDelay <= 0) {
            $this->io->error('partner-delay must be greater than 0');

            return null;
        }

        return new ScrapeConfig(
            zone: $zone,
            outputFile: $input->getOption('output-file'),
            pageDelay: $pageDelay,
            partnerDelay: $partnerDelay,
            insecure: (bool) $input->getOption('insecure'),
            resume: (bool) $input->getOption('resume'),
            fullRefresh: (bool) $input->getOption('full-refresh'),
        );
    }

    private function executeFullScrape(ScrapeConfig $config): int
    {
        if (!$config->resume && !$config->fullRefresh && file_exists($config->outputFile)) {
            $this->io->error(sprintf('Файл %s уже существует. Используйте --full-refresh для перезаписи.', $config->outputFile));

            return Command::FAILURE;
        }

        $onVerbose = $this->io->isVerbose()
            ? fn (string $message) => $this->io->text($message)
            : null;

        $context = $this->workflow->resolveStartContext($config, $onVerbose);
        if (null === $context) {
            $this->io->error('State-файл не найден. Запустите без --resume.');

            return Command::FAILURE;
        }

        $startPage = $context['startPage'];
        $lastPage = $context['lastPage'];
        $processedNumbers = $context['processedNumbers'];
        $partnersPerPage = $context['partnersPerPage'];

        if ($config->resume) {
            if ($this->io->isVerbose()) {
                $this->io->note(sprintf(
                    'Resume: продолжаем со страницы %d из %d (уже обработано: %d)',
                    $startPage,
                    $lastPage,
                    count($processedNumbers)
                ));
            }
        } elseif ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->section('Определение количества страниц...');
            $this->io->success(sprintf(
                'Найдено страниц: %d | Партнёров на странице: %d (≈%d партнёров)',
                $lastPage,
                $partnersPerPage,
                $lastPage * $partnersPerPage
            ));
            $this->io->section('Парсинг партнёров...');
        }

        $progressBar = $this->createProgressBar($lastPage * $partnersPerPage, count($processedNumbers));

        $onProgress = function (string $event, int $value) use ($progressBar): void {
            match ($event) {
                'page_start' => $progressBar?->setMessage((string) $value, 'page'),
                'partner_start' => $progressBar?->setMessage((string) $value, 'partner'),
                'partner_advance' => $progressBar?->advance(),
                default => null,
            };
        };

        $result = $this->workflow->run(
            $config,
            $startPage,
            $lastPage,
            $processedNumbers,
            $onProgress,
        );

        return $this->finishScrape($config->outputFile, $progressBar, $result);
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

            if ($result->skippedNoDetailPage > 0) {
                $this->io->note(sprintf(
                    'Пропущено без детальной страницы: %d (ID: %s)',
                    $result->skippedNoDetailPage,
                    implode(', ', $result->skippedPartnerNumbers),
                ));
            }

            return Command::FAILURE;
        }

        $this->workflow->complete($outputFile);

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->success(sprintf('Парсинг завершён. Обработано партнёров: %d', $result->totalProcessed));

            if ($result->skippedNoDetailPage > 0) {
                $this->io->note(sprintf(
                    'Пропущено без детальной страницы: %d (ID: %s)',
                    $result->skippedNoDetailPage,
                    implode(', ', $result->skippedPartnerNumbers),
                ));
            }
        }

        return Command::SUCCESS;
    }
}
