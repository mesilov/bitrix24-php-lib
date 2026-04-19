<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Create\Command as CreateCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Create\Handler as CreateHandler;
use League\Csv\Reader;
use League\Csv\Statement;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
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
    public function __construct(
        private readonly CreateHandler $createHandler
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
        $symfonyStyle = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        $skipErrors = $input->getOption('skip-errors');

        if (!file_exists($file)) {
            $symfonyStyle->error(sprintf('File not found: %s', $file));

            return Command::FAILURE;
        }

        $symfonyStyle->title('Importing Bitrix24 Partners from CSV');
        $symfonyStyle->info(sprintf('Reading file: %s', $file));

        try {
            $imported = $this->importFromCsv($file, $skipErrors, $symfonyStyle, $output);

            $symfonyStyle->success(sprintf('Successfully imported %d partners', $imported));

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $symfonyStyle->error(sprintf('Error: %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }

    private function importFromCsv(string $file, bool $skipErrors, SymfonyStyle $io, OutputInterface $output): int
    {
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);

        $phoneUtil = PhoneNumberUtil::getInstance();
        $imported = 0;
        $skipped = 0;

        // Validate header
        $expectedHeaders = ['title', 'site', 'phone', 'email', 'bitrix24_partner_number', 'open_line_id', 'external_id'];
        $actualHeaders = $csv->getHeader();
        if ($actualHeaders !== $expectedHeaders) {
            $io->warning(sprintf(
                'CSV header mismatch. Expected: %s, Got: %s',
                implode(', ', $expectedHeaders),
                implode(', ', $actualHeaders)
            ));
        }

        // Get records
        $records = Statement::create()->process($csv);
        $totalRecords = iterator_count($records);

        if (0 === $totalRecords) {
            $io->warning('No records found in CSV file');

            return 0;
        }

        // Reset iterator
        $records = Statement::create()->process($csv);

        // Create progress bar
        $progressBar = new ProgressBar($output, $totalRecords);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $lineNumber = 1; // Header is line 1

        foreach ($records as $record) {
            ++$lineNumber;
            $progressBar->advance();

            try {
                // Skip empty rows
                if ([] === array_filter($record)) {
                    continue;
                }

                // Parse row data
                $title = isset($record['title']) ? trim((string) $record['title']) : '';
                $siteRaw = isset($record['site']) ? trim((string) $record['site']) : '';
                $site = '' !== $siteRaw ? $siteRaw : null;
                $phoneStringRaw = isset($record['phone']) ? trim((string) $record['phone']) : '';
                $phoneString = '' !== $phoneStringRaw ? $phoneStringRaw : null;
                $emailRaw = isset($record['email']) ? trim((string) $record['email']) : '';
                $email = '' !== $emailRaw ? $emailRaw : null;
                $bitrix24PartnerNumberRaw = isset($record['bitrix24_partner_number']) ? trim((string) $record['bitrix24_partner_number']) : '';
                $bitrix24PartnerNumber = '' !== $bitrix24PartnerNumberRaw ? (int) $bitrix24PartnerNumberRaw : null;
                $openLineIdRaw = isset($record['open_line_id']) ? trim((string) $record['open_line_id']) : '';
                $openLineId = '' !== $openLineIdRaw ? $openLineIdRaw : null;
                $externalIdRaw = isset($record['external_id']) ? trim((string) $record['external_id']) : '';
                $externalId = '' !== $externalIdRaw ? $externalIdRaw : null;

                // Validate required fields
                if ('' === $title) {
                    throw new \InvalidArgumentException('Title is required');
                }

                if (null === $bitrix24PartnerNumber) {
                    throw new \InvalidArgumentException('Bitrix24 Partner Number is required');
                }

                // Parse phone number
                $phone = null;
                if (null !== $phoneString) {
                    try {
                        $phone = $phoneUtil->parse($phoneString, 'RU');
                    } catch (NumberParseException $e) {
                        if (!$skipErrors) {
                            throw new \InvalidArgumentException(
                                sprintf('Invalid phone number: %s', $phoneString),
                                0,
                                $e
                            );
                        }

                        $phone = null;
                    }
                }

                // Create partner
                $command = new CreateCommand(
                    $title,
                    $bitrix24PartnerNumber,
                    $site,
                    $phone,
                    $email,
                    $openLineId,
                    $externalId
                );

                $this->createHandler->handle($command);
                ++$imported;
            } catch (\Exception $e) {
                if (!$skipErrors) {
                    $progressBar->finish();

                    throw new \RuntimeException(
                        sprintf('Error on line %d: %s', $lineNumber, $e->getMessage()),
                        0,
                        $e
                    );
                }

                ++$skipped;
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($skipped > 0) {
            $io->note(sprintf('Skipped %d rows due to errors', $skipped));
        }

        return $imported;
    }
}
