<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerCsvStorage;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\PartnerPageScraper;
use League\Csv\Reader;
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
    description: 'Обновляет данные конкретных партнёров из CSV по ID'
)]
class UpdatePartnersCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PartnerPageScraper $scraper,
        private readonly PartnerCsvStorage $csvStorage,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('partner-ids', null, InputOption::VALUE_REQUIRED, 'ID партнёров через запятую', '')
            ->addOption('partner-ids-from-file', null, InputOption::VALUE_REQUIRED, 'Файл с номерами партнёров для обновления', '')
            ->addOption('output-file', null, InputOption::VALUE_REQUIRED, 'Путь к CSV файлу', 'partners.csv')
            ->addOption('partner-delay', null, InputOption::VALUE_REQUIRED, 'Задержка между партнёрами (сек)', '2')
            ->addOption('insecure', null, InputOption::VALUE_NONE, 'Отключить проверку SSL (для dev)')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $partnerIds = $input->getOption('partner-ids');
        $partnerIdsFromFile = $input->getOption('partner-ids-from-file');
        $outputFile = $input->getOption('output-file');
        $partnerDelay = (int) $input->getOption('partner-delay');
        $insecure = (bool) $input->getOption('insecure');

        try {
            if ('' !== $partnerIds) {
                return $this->executePartnerUpdate(
                    explode(',', (string) $partnerIds),
                    $outputFile,
                    $partnerDelay,
                    $insecure,
                    $io,
                    $output
                );
            }

            if ('' !== $partnerIdsFromFile) {
                return $this->executePartnerUpdateFromFile(
                    $partnerIdsFromFile,
                    $outputFile,
                    $partnerDelay,
                    $insecure,
                    $io,
                    $output
                );
            }

            $io->error('Укажите --partner-ids или --partner-ids-from-file');

            return Command::FAILURE;
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка: '.$throwable->getMessage());
            $io->error('Ошибка: '.$throwable->getMessage());

            return Command::FAILURE;
        }
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
        OutputInterface $output,
    ): int {
        if (!file_exists($outputFile)) {
            $io->error(sprintf('CSV файл %s не найден. Сначала выполните полную выгрузку.', $outputFile));

            return Command::FAILURE;
        }

        $partnerIds = array_map('intval', array_filter(array_map('trim', $partnerIdList)));
        if ([] === $partnerIds) {
            $io->error('Список ID партнёров пуст.');

            return Command::FAILURE;
        }

        $io->text(sprintf('Обновление %d партнёров...', count($partnerIds)));

        $records = $this->csvStorage->readAsPartnerMap($outputFile);

        $progressBar = new ProgressBar($output, count($partnerIds));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Партнёр: %partner% | %message%');
        $progressBar->setMessage('', 'partner');
        $progressBar->setMessage('');
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        foreach ($partnerIds as $partnerId) {
            $progressBar->setMessage((string) $partnerId, 'partner');
            $progressBar->advance();

            if (!isset($records[$partnerId])) {
                $this->logger->warning(sprintf('Партнёр #%d не найден в CSV, пропускаем', $partnerId));
                ++$errors;

                continue;
            }

            $baseDomain = $records[$partnerId]['base_domain'] ?? '';

            try {
                $partnerData = $this->scraper->fetchPartnerData($partnerId, $baseDomain, $insecure);
                if (null === $partnerData) {
                    $this->logger->warning(sprintf('Партнёр #%d: не удалось загрузить данные', $partnerId));
                    ++$errors;

                    continue;
                }

                $records[$partnerId]['phone'] = $partnerData->phone ?? '';
                $records[$partnerId]['email'] = $partnerData->email ?? '';
                $records[$partnerId]['logo_url'] = $partnerData->logoUrl ?? '';
                $records[$partnerId]['site'] = $partnerData->site ?? '';
                $records[$partnerId]['scraped_at'] = $partnerData->scrapedAt->format(\DateTimeInterface::ATOM);

                ++$updated;
                $progressBar->setMessage('OK');
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка обновления партнёра #%d: %s', $partnerId, $e->getMessage()));
                $progressBar->setMessage('Ошибка');
                ++$errors;
            }

            sleep($partnerDelay);
        }

        $progressBar->finish();
        $io->newLine(2);

        $this->csvStorage->writeAll($outputFile, $records);

        $io->success(sprintf('Обновлено: %d, ошибок: %d', $updated, $errors));

        return Command::SUCCESS;
    }

    private function executePartnerUpdateFromFile(
        string $idsFilePath,
        string $outputFile,
        int $partnerDelay,
        bool $insecure,
        SymfonyStyle $io,
        OutputInterface $output,
    ): int {
        if (!file_exists($idsFilePath)) {
            $io->error(sprintf('Файл с ID партнёров не найден: %s', $idsFilePath));

            return Command::FAILURE;
        }

        $reader = Reader::from($idsFilePath);
        $partnerIds = [];
        foreach ($reader->getRecords() as $record) {
            $id = (int) trim((string) array_values($record)[0]);
            if ($id > 0) {
                $partnerIds[] = (string) $id;
            }
        }

        if ([] === $partnerIds) {
            $io->error('Файл не содержит ID партнёров.');

            return Command::FAILURE;
        }

        return $this->executePartnerUpdate($partnerIds, $outputFile, $partnerDelay, $insecure, $io, $output);
    }
}
