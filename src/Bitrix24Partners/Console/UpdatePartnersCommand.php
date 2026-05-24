<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeResult;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\UpdateConfig;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\UpdateWorkflow;
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
    name: 'partners:update',
    description: 'Обновляет данные конкретных партнёров по ID с сайта Bitrix24'
)]
class UpdatePartnersCommand extends Command
{
    private SymfonyStyle $io;

    private OutputInterface $output;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UpdateWorkflow $workflow,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('partner-ids', null, InputOption::VALUE_REQUIRED, 'ID партнёров через запятую', '')
            ->addOption('output-file', null, InputOption::VALUE_REQUIRED, 'Путь к выходному CSV файлу', 'partners_update.csv')
            ->addOption('zone', null, InputOption::VALUE_REQUIRED, 'Зона Bitrix24 (ru, kz)', 'ru')
            ->addOption('partner-detail-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между карточками партнёров (сек)', '2')
            ->addOption('insecure', null, InputOption::VALUE_NONE, 'Отключить проверку SSL (для dev)')
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
            $this->io->text(sprintf('Partner IDs: %s', implode(', ', $config->partnerIds)));
            $this->io->text(sprintf('Output file: %s', $config->outputFile));
            $this->io->text(sprintf('Zone: %s', $config->zone->value));
            $this->io->text(sprintf('Partner detail delay: %d sec', $config->partnerDetailDelay));
            $this->io->text(sprintf('Insecure: %s', $config->insecure ? 'yes' : 'no'));
        }

        try {
            return $this->executeUpdate($config);
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $this->io->error('Ошибка: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }

    private function resolveConfig(InputInterface $input): ?UpdateConfig
    {
        $partnerIdsRaw = $input->getOption('partner-ids');
        if ('' === $partnerIdsRaw) {
            $this->io->error('Укажите --partner-ids');

            return null;
        }

        $partnerIds = [];
        $parts = array_map('trim', explode(',', (string) $partnerIdsRaw));
        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                $this->io->error(sprintf('Невалидный ID партнёра: "%s". Ожидается положительное число.', $part));

                return null;
            }

            $partnerIds[] = (int) $part;
        }

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

        return new UpdateConfig(
            partnerIds: $partnerIds,
            outputFile: $input->getOption('output-file'),
            zone: $zone,
            partnerDetailDelay: $partnerDetailDelay,
            insecure: (bool) $input->getOption('insecure'),
        );
    }

    private function executeUpdate(UpdateConfig $config): int
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            $this->io->text(sprintf('Обновление %d партнёров...', count($config->partnerIds)));
        }

        $progressBar = $this->createProgressBar(count($config->partnerIds));

        $onProgress = function (string $event, int $value) use ($progressBar): void {
            match ($event) {
                'partner_start' => $progressBar?->setMessage((string) $value, 'partner'),
                'partner_advance' => $progressBar?->advance(),
                default => null,
            };
        };

        $result = $this->workflow->run($config, $onProgress);

        return $this->finishUpdate($progressBar, $result);
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

    private function createProgressBar(int $total): ?ProgressBar
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
}
