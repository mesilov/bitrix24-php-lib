<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeResult;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\UpdateConfig;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\UpdateWorkflow;
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
            ->addOption('base-domain', null, InputOption::VALUE_REQUIRED, 'Домен Bitrix24', 'https://www.bitrix24.ru')
            ->addOption('partner-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между партнёрами (сек)', '2')
            ->addOption('insecure', null, InputOption::VALUE_NONE, 'Отключить проверку SSL (для dev)')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;

        $partnerIdsRaw = $input->getOption('partner-ids');
        if ('' === $partnerIdsRaw) {
            $this->io->error('Укажите --partner-ids');

            return Command::FAILURE;
        }

        $partnerIds = array_map('intval', array_filter(array_map('trim', explode(',', (string) $partnerIdsRaw))));
        if ([] === $partnerIds) {
            $this->io->error('Список ID партнёров пуст.');

            return Command::FAILURE;
        }

        $config = new UpdateConfig(
            partnerIds: $partnerIds,
            outputFile: $input->getOption('output-file'),
            baseDomain: $input->getOption('base-domain'),
            delay: (int) $input->getOption('partner-delay'),
            insecure: (bool) $input->getOption('insecure'),
        );

        try {
            return $this->executeUpdate($config);
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $this->io->error('Ошибка: '.$throwable->getMessage());

            return Command::FAILURE;
        }
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
            $this->io->success(sprintf(
                'Обновлено: %d, ошибок: %d',
                $result->totalProcessed,
                $result->totalEmptyPages,
            ));
        }

        return Command::SUCCESS;
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
