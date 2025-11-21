<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Create\Command as CreateCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Create\Handler as CreateHandler;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        $skipErrors = $input->getOption('skip-errors');

        if (!file_exists($file)) {
            $io->error(sprintf('File not found: %s', $file));

            return Command::FAILURE;
        }

        $io->title('Importing Bitrix24 Partners from CSV');
        $io->info(sprintf('Reading file: %s', $file));

        try {
            $imported = $this->importFromCsv($file, $skipErrors, $io);

            $io->success(sprintf('Successfully imported %d partners', $imported));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function importFromCsv(string $file, bool $skipErrors, SymfonyStyle $io): int
    {
        $fp = fopen($file, 'r');
        if (false === $fp) {
            throw new \RuntimeException(sprintf('Cannot open file: %s', $file));
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $imported = 0;
        $skipped = 0;
        $lineNumber = 0;

        // Read header
        $header = fgetcsv($fp);
        if (false === $header) {
            fclose($fp);
            throw new \RuntimeException('CSV file is empty');
        }

        $lineNumber++;

        // Validate header
        $expectedHeaders = ['title', 'site', 'phone', 'email', 'bitrix24_partner_id', 'open_line_id', 'external_id'];
        if ($header !== $expectedHeaders) {
            $io->warning(sprintf(
                'CSV header mismatch. Expected: %s, Got: %s',
                implode(', ', $expectedHeaders),
                implode(', ', $header)
            ));
        }

        // Process rows
        while (false !== ($row = fgetcsv($fp))) {
            $lineNumber++;

            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Parse row data
                $title = trim($row[0] ?? '');
                $site = !empty($row[1] ?? '') ? trim($row[1]) : null;
                $phoneString = !empty($row[2] ?? '') ? trim($row[2]) : null;
                $email = !empty($row[3] ?? '') ? trim($row[3]) : null;
                $bitrix24PartnerId = !empty($row[4] ?? '') ? (int) $row[4] : null;
                $openLineId = !empty($row[5] ?? '') ? trim($row[5]) : null;
                $externalId = !empty($row[6] ?? '') ? trim($row[6]) : null;

                // Validate required fields
                if (empty($title)) {
                    throw new \InvalidArgumentException('Title is required');
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
                        $io->warning(sprintf('Line %d: Invalid phone number "%s", skipping phone', $lineNumber, $phoneString));
                        $phone = null;
                    }
                }

                // Create partner
                $command = new CreateCommand(
                    $title,
                    $site,
                    $phone,
                    $email,
                    $bitrix24PartnerId,
                    $openLineId,
                    $externalId
                );

                $this->createHandler->handle($command);
                $imported++;

                $io->writeln(sprintf('Imported: %s', $title));
            } catch (\Exception $e) {
                if (!$skipErrors) {
                    fclose($fp);
                    throw new \RuntimeException(
                        sprintf('Error on line %d: %s', $lineNumber, $e->getMessage()),
                        0,
                        $e
                    );
                }

                $skipped++;
                $io->warning(sprintf('Line %d: Skipped due to error: %s', $lineNumber, $e->getMessage()));
            }
        }

        fclose($fp);

        if ($skipped > 0) {
            $io->note(sprintf('Skipped %d rows due to errors', $skipped));
        }

        return $imported;
    }
}
