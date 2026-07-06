<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeConfig;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeOptions;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeProgress;
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
    private const int DEFAULT_REQUEST_DELAY = 2;

    private SymfonyStyle $io;

    private OutputInterface $output;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScrapeWorkflow $scrapeWorkflow,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('zone', null, InputOption::VALUE_REQUIRED, 'Зона Bitrix24 (ru, kz)', 'ru')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Относительный путь к папке для CSV файлов от корня проекта, например: var/scraper (обязательный)')
            ->addOption('request-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между HTTP-запросами (сек)', (string) self::DEFAULT_REQUEST_DELAY)
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

        $options = $this->resolveOptions($input);
        if (null === $options) {
            return Command::FAILURE;
        }

        try {
            if ($options->isUpdateMode()) {
                $outputFile = ScrapeConfig::getOutputPath($options->outputDir);
                $config = new ScrapeConfig(
                    zone: $options->zone,
                    outputDir: $options->outputDir,
                    requestDelay: $options->requestDelay,
                    insecure: $options->insecure,
                    resume: false,
                    partnerIds: $options->partnerIds,
                    outputFile: $outputFile,
                );

                return $this->executeUpdate($config);
            }

            return $this->executeFullScrape($options);
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $this->io->error('Ошибка: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }

    private function resolveOptions(InputInterface $input): ?ScrapeOptions
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

        $requestDelay = (int) $input->getOption('request-delay');
        if ($requestDelay <= 0) {
            $this->io->error('request-delay must be greater than 0');

            return null;
        }

        $partnerIds = null;
        $partnerIdsRaw = $input->getOption('partner-ids');
        if ('' !== $partnerIdsRaw) {
            $partnerIds = [];
            $parts = array_map(trim(...), explode(',', (string) $partnerIdsRaw));
            foreach ($parts as $part) {
                $partnerId = (int) $part;
                if ($partnerId <= 0 || (string) $partnerId !== $part) {
                    $this->io->error(sprintf('Невалидный ID партнёра: "%s". Ожидается положительное число.', $part));

                    return null;
                }

                $partnerIds[] = $partnerId;
            }
        }

        $outputDir = $input->getOption('output-dir');
        if (null === $outputDir) {
            $this->io->error('Не указан обязательный параметр --output-dir');

            return null;
        }

        $resolvedDir = $this->resolveOutputDir($outputDir);
        if (null === $resolvedDir) {
            return null;
        }

        return new ScrapeOptions(
            zone: $zone,
            outputDir: $resolvedDir,
            requestDelay: $requestDelay,
            insecure: (bool) $input->getOption('insecure'),
            resume: (bool) $input->getOption('resume'),
            partnerIds: $partnerIds,
        );
    }

    /**
     * Резолвит относительный путь от корня проекта; абсолютные пути не поддерживаются
     * (они привязаны к окружению и по-разному выглядят в Docker и на хосте).
     * Создаёт директорию при необходимости и канонизирует через realpath().
     *
     * @return null|string Абсолютный канонизированный путь или null при ошибке
     */
    private function resolveOutputDir(string $outputDir): ?string
    {
        if (str_starts_with($outputDir, '/')) {
            $this->io->error(sprintf(
                'Абсолютные пути не поддерживаются — используйте относительный путь от корня проекта, например: var/scraper (получено: %s)',
                $outputDir,
            ));

            return null;
        }

        $resolvedPath = $this->projectRoot.'/'.$outputDir;

        if (!is_dir($resolvedPath) && !mkdir($resolvedPath, 0755, true) && !is_dir($resolvedPath)) {
            $this->io->error(sprintf('Не удалось создать директорию: %s', $resolvedPath));

            return null;
        }

        $realPath = realpath($resolvedPath);
        if (false === $realPath) {
            $this->io->error(sprintf('Не удалось определить путь к директории: %s', $resolvedPath));

            return null;
        }

        return $realPath;
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

    private function executeFullScrape(ScrapeOptions $options): int
    {
        $onVerbose = $this->io->isVerbose()
            ? $this->io->text(...)
            : null;

        $outputFile = null;
        $startPage = 1;
        $lastPage = 0;
        $progress = new ScrapeProgress();
        $partnersPerPage = 12;

        if ($options->resume) {
            $resumeState = $this->scrapeWorkflow->resolveResumeContext($options->outputDir, $options->zone->value);
            if (null === $resumeState) {
                $this->io->error('State-файл не найден. Запустите без --resume.');

                return Command::FAILURE;
            }

            $outputFile = $resumeState['outputFile'];
            $startPage = $resumeState['startPage'];
            $lastPage = $resumeState['lastPage'];
            $progress = new ScrapeProgress($resumeState['processedNumbers']);
        } else {
            $outputFile = ScrapeConfig::getOutputPath($options->outputDir);
            $range = $this->scrapeWorkflow->getPageRange($options->zone, $options->insecure, $onVerbose);
            $lastPage = $range['lastPage'];
            $partnersPerPage = $range['partnersPerPage'];
        }

        $config = new ScrapeConfig(
            zone: $options->zone,
            outputDir: $options->outputDir,
            requestDelay: $options->requestDelay,
            insecure: $options->insecure,
            resume: $options->resume,
            partnerIds: $options->partnerIds,
            outputFile: $outputFile,
        );

        if ($this->io->isVerbose()) {
            $this->io->text(sprintf('Zone: %s', $config->zone->value));
            $this->io->text(sprintf('Base URL: %s', $config->baseUrl));
            $this->io->text(sprintf('Output dir: %s', $config->outputDir));
            $this->io->text(sprintf('Output file: %s', $config->outputFile));
            $this->io->text(sprintf('Request delay: %d sec', $config->requestDelay));
            $this->io->text(sprintf('Insecure: %s', $config->insecure ? 'yes' : 'no'));
            $this->io->text(sprintf('Resume: %s', $config->resume ? 'yes' : 'no'));

            if ($options->resume) {
                $this->io->note(sprintf(
                    'Resume: продолжаем со страницы %d из %d (уже обработано: %d)',
                    $startPage,
                    $lastPage,
                    $progress->totalProcessed
                ));
            }
        }

        if (!$options->resume && $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->section('Определение количества страниц...');
            $this->io->success(sprintf(
                'Найдено страниц: %d | Партнёров на странице: %d (≈%d партнёров)',
                $lastPage,
                $partnersPerPage,
                $lastPage * $partnersPerPage
            ));
            $this->io->section('Парсинг партнёров...');
        }

        $progressBar = $this->createScrapeProgressBar($lastPage * $partnersPerPage, $progress->totalProcessed);

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
            $progress,
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
                'Парсинг прерван. Обработано партнёров: %d | Пустых страниц: %d из %d. Возможно, доступ заблокирован — увеличьте задержку (--request-delay) и попробуйте позже.',
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
