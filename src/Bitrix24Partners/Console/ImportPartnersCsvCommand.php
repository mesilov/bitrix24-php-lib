<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Import\ImportConfig;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Import\ImportResult;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Import\ImportWorkflow;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bitrix24:partners:import',
    description: 'Import partners from CSV file into database'
)]
class ImportPartnersCsvCommand extends Command
{
    private SymfonyStyle $io;

    private OutputInterface $output;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ImportWorkflow $workflow,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Path to CSV file to import'
            )
            ->addOption(
                'sync-mode',
                null,
                InputOption::VALUE_REQUIRED,
                'Sync mode: full (CSV = complete dataset) or partial (CSV = patch only)',
                'full'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be done without making changes'
            )
            ->addOption(
                'skip-errors',
                's',
                InputOption::VALUE_NONE,
                'Skip rows with errors and continue processing'
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;

        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $this->io->error(sprintf('File not found: %s', $file));

            return Command::FAILURE;
        }

        $config = new ImportConfig(
            file: $file,
            syncMode: $input->getOption('sync-mode'),
            dryRun: (bool) $input->getOption('dry-run'),
            skipErrors: (bool) $input->getOption('skip-errors'),
        );

        if ($this->io->isVerbose()) {
            $this->io->text(sprintf('File: %s', $config->file));
            $this->io->text(sprintf('Sync mode: %s', $config->syncMode));
            $this->io->text(sprintf('Dry run: %s', $config->dryRun ? 'yes' : 'no'));
        }

        try {
            return $this->executeImport($config);
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $this->io->error('Ошибка: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }

    private function executeImport(ImportConfig $config): int
    {
        $this->io->title('Importing Bitrix24 Partners from CSV');

        $onVerbose = $this->io->isVerbose()
            ? fn (string $message) => $this->io->text($message)
            : null;

        $progressBar = $this->createProgressBar();

        $onProgress = function (string $event, int $value) use ($progressBar): void {
            match ($event) {
                'csv_total' => (function () use ($progressBar, $value) {
                    $progressBar?->setMaxSteps($value);
                    $progressBar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
                })(),
                'row_advance' => $progressBar?->advance(),
                default => null,
            };
        };

        $result = $this->workflow->run($config, $onProgress, $onVerbose);

        return $this->finishImport($progressBar, $result);
    }

    private function createProgressBar(): ?ProgressBar
    {
        if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_NORMAL) {
            return null;
        }

        $progressBar = new ProgressBar($this->output);
        $progressBar->setFormat(' %current% [%bar%] %elapsed:6s% %memory:6s%');
        $progressBar->start();

        return $progressBar;
    }

    private function finishImport(?ProgressBar $progressBar, ImportResult $result): int
    {
        $progressBar?->finish();
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->newLine(2);
        }

        if ($result->dryRun) {
            $this->io->note('DRY RUN — изменения не применены');
            foreach ($result->plannedActions as $action) {
                $this->io->text(sprintf(
                    '  %s #%d %s%s',
                    $action['action'],
                    $action['partnerNumber'],
                    $action['title'],
                    isset($action['details']) ? ' ('.$action['details'].')' : ''
                ));
            }
        }

        $this->io->success(sprintf(
            'Created: %d | Updated: %d | Skipped: %d | Soft-deleted: %d | Errors: %d',
            $result->created,
            $result->updated,
            $result->skipped,
            $result->softDeleted,
            $result->errors,
        ));

        return $result->errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
