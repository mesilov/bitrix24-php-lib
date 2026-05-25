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
    description: 'Парсит партнеров Bitrix24 и сохраняет данные в CSV. С --partner-ids — обновляет конкретных партнёров.'
)]
class ScrapePartnersCommand extends Command
{
    private const string DEFAULT_OUTPUT_DIR = 'var/scraper';

    private const int DEFAULT_CATALOG_PAGE_DELAY = 2;

    private const int DEFAULT_PARTNER_DETAIL_DELAY = 2;

    private SymfonyStyle $io;

    private OutputInterface $output;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScrapeWorkflow $scrapeWorkflow,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('zone', null, InputOption::VALUE_REQUIRED, 'Зона Bitrix24 (ru, kz)', 'ru')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Путь к папке для CSV файлов', self::DEFAULT_OUTPUT_DIR)
            ->addOption('catalog-page-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между страницами каталога (сек)', (string) self::DEFAULT_CATALOG_PAGE_DELAY)
            ->addOption('partner-detail-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между карточками партнёров (сек)', (string) self::DEFAULT_PARTNER_DETAIL_DELAY)
            ->addOption('insecure', null, InputOption::VALUE_NONE, 'Отключить проверку SSL (для dev)')
            ->addOption('partner-ids', null, InputOption::VALUE_REQUIRED, 'ID партнёров через запятую (режим обновления)', '')
            ->addOption('resume', null, InputOption::VALUE_NONE, 'Продолжить с места обрыва (из state.json)')
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
            $this->io->text(sprintf('Output dir: %s', $config->outputDir));
            $this->io->text(sprintf('Output file: %s', $config->outputFile));
            $this->io->text(sprintf('Partner detail delay: %d sec', $config->partnerDetailDelay));
            $this->io->text(sprintf('Insecure: %s', $config->insecure ? 'yes' : 'no'));

            if ($config->isUpdateMode()) {
                $this->io->text(sprintf('Partner IDs: %s', implode(', ', $config->partnerIds)));
            } else {
                $this->io->text(sprintf('Catalog page delay: %d sec', $config->catalogPageDelay));
                $this->io->text(sprintf('Resume: %s', $config->resume ? 'yes' : 'no'));
            }
        }

        try {
            if ($config->isUpdateMode()) {
                return $this->executeUpdate($config);
            }

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

        $partnerDetailDelay = (int) $input->getOption('partner-detail-delay');
        if ($partnerDetailDelay <= 0) {
            $this->io->error('partner-detail-delay must be greater than 0');

            return null;
        }

        $partnerIds = null;
        $partnerIdsRaw = $input->getOption('partner-ids');
        if ('' !== $partnerIdsRaw) {
            $partnerIds = [];
            $parts = array_map('trim', explode(',', (string) $partnerIdsRaw));
            foreach ($parts as $part) {
                if (!ctype_digit($part)) {
                    $this->io->error(sprintf('Невалидный ID партнёра: "%s". Ожидается положительное число.', $part));

                    return null;
                }

                $partnerIds[] = (int) $part;
            }
        }

        $catalogPageDelay = (int) $input->getOption('catalog-page-delay');
        if (null === $partnerIds && $catalogPageDelay <= 0) {
            $this->io->error('catalog-page-delay must be greater than 0');

            return null;
        }

        $outputDir = $input->getOption('output-dir');
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                $this->io->error(sprintf('Не удалось создать директорию: %s', $outputDir));

                return null;
            }
        }

        return new ScrapeConfig(
            zone: $zone,
            outputDir: $outputDir,
            catalogPageDelay: $catalogPageDelay,
            partnerDetailDelay: $partnerDetailDelay,
            insecure: (bool) $input->getOption('insecure'),
            resume: (bool) $input->getOption('resume'),
            partnerIds: $partnerIds,
        );
    }

    private function executeUpdate(ScrapeConfig $config): int
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->text(sprintf('Обновление %d партнёров...', count($config->partnerIds)));
        }

        $progressBar = $this->createUpdateProgressBar(count($config->partnerIds));

        $onProgress = function (string $event, int $value) use ($progressBar): void {
            match ($event) {
                'partner_start' => $progressBar?->setMessage((string) $value, 'partner'),
                'partner_advance' => $progressBar?->advance(),
                default => null,
            };
        };

        $result = $this->scrapeWorkflow->runUpdate($config, $onProgress);

        return $this->finishUpdate($progressBar, $result);
    }

    private function executeFullScrape(ScrapeConfig $config): int
    {
        $onVerbose = $this->io->isVerbose()
            ? fn (string $message) => $this->io->text($message)
            : null;

        $context = $this->scrapeWorkflow->resolveStartContext($config, $onVerbose);
        if (null === $context) {
            $this->io->error('State-файл не найден. Запустите без --resume.');

            return Command::FAILURE;
        }

        $startPage = $context['startPage'];
        $lastPage = $context['lastPage'];
        $processedNumbers = $context['processedNumbers'];
        $partnersPerPage = $context['partnersPerPage'];
        $outputFile = $context['outputFile'];

        if ($config->resume) {
            $resumeConfig = new ScrapeConfig(
                zone: $config->zone,
                outputDir: $config->outputDir,
                catalogPageDelay: $config->catalogPageDelay,
                partnerDetailDelay: $config->partnerDetailDelay,
                insecure: $config->insecure,
                resume: true,
                partnerIds: $config->partnerIds,
                outputFile: $outputFile,
            );
            $config = $resumeConfig;

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

        $progressBar = $this->createScrapeProgressBar($lastPage * $partnersPerPage, count($processedNumbers));

        $onProgress = function (string $event, int $value) use ($progressBar): void {
            match ($event) {
                'page_start' => $progressBar?->setMessage((string) $value, 'page'),
                'partner_start' => $progressBar?->setMessage((string) $value, 'partner'),
                'partner_advance' => $progressBar?->advance(),
                default => null,
            };
        };

        $result = $this->scrapeWorkflow->run(
            $config,
            $startPage,
            $lastPage,
            $processedNumbers,
            $onProgress,
        );

        return $this->finishScrape($config->outputDir, $progressBar, $result);
    }

    private function createScrapeProgressBar(int $total, int $alreadyProcessed): ?ProgressBar
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

    private function createUpdateProgressBar(int $total): ?ProgressBar
    {
        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_NORMAL) {
            return null;
        }

        $progressBar = new ProgressBar($this->output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Партнёр: %partner%');
        $progressBar->setMessage('', 'partner');
        $progressBar->start();

        return $progressBar;
    }

    private function finishScrape(string $outputDir, ?ProgressBar $progressBar, ScrapeResult $result): int
    {
        $progressBar?->finish();
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->newLine(2);
        }

        if ($result->banDetected) {
            $this->io->warning(sprintf(
                'Парсинг прерван. Обработано партнёров: %d | Пустых страниц: %d из %d. Возможно, доступ заблокирован — увеличьте задержки (--partner-detail-delay, --catalog-page-delay) и попробуйте позже.',
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

        $this->scrapeWorkflow->complete($outputDir);

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

    private function finishUpdate(?ProgressBar $progressBar, ScrapeResult $result): int
    {
        $progressBar?->finish();
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->newLine(2);

            if ($result->banDetected) {
                $this->io->warning(sprintf(
                    'Обнаружена блокировка. Обновлено: %d, ошибок: %d',
                    $result->totalProcessed,
                    $result->totalEmptyPages,
                ));
            } else {
                $this->io->success(sprintf(
                    'Обновлено: %d, ошибок: %d',
                    $result->totalProcessed,
                    $result->totalEmptyPages,
                ));
            }
        }

        return $result->banDetected ? Command::FAILURE : Command::SUCCESS;
    }
}
