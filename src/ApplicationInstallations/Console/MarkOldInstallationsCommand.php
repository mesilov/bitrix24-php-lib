<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Console;

use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations\MarkOldInstallationsConfig;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations\Workflow;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations\MarkOldInstallationsResult;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bitrix24:installations:mark-old',
    description: 'Mark stale pending installations as needReinstall'
)]
class MarkOldInstallationsCommand extends Command
{
    public const DEFAULT_TTL = 3600;

    private ?SymfonyStyle $io = null;

    public function __construct(
        private readonly Workflow $workflow
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'ttl',
                InputArgument::OPTIONAL,
                sprintf('Time to live in seconds (default: %d)', self::DEFAULT_TTL),
                (string) self::DEFAULT_TTL
            )
            ->addOption(
                'member-id',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Filter by specific portal member ID'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be marked without making changes'
            )
            ->setHelp(
                <<<'HELP'
The <info>bitrix24:installations:mark-old</info> command finds pending installations
in status "new" older than the given TTL and marks them as "needReinstall".

<comment>Mark installations older than 1 hour (default):</comment>
  <info>php bin/console bitrix24:installations:mark-old</info>

<comment>Mark installations older than 30 minutes:</comment>
  <info>php bin/console bitrix24:installations:mark-old 1800</info>

<comment>Mark installations for a specific portal:</comment>
  <info>php bin/console bitrix24:installations:mark-old 3600 --member-id=xxxxxxxxxxxxxxxxx</info>

<comment>Dry run — show what would be marked:</comment>
  <info>php bin/console bitrix24:installations:mark-old --dry-run</info>
HELP
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $config = $this->parseInput($input);
        if (null === $config) {
            return Command::FAILURE;
        }

        $result = $this->workflow->run($config);

        return $this->renderResult($result);
    }

    private function parseInput(InputInterface $input): ?MarkOldInstallationsConfig
    {
        $ttl = (int) $input->getArgument('ttl');
        $memberId = $input->getOption('member-id');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            return new MarkOldInstallationsConfig($ttl, $memberId, $dryRun);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->io->error($invalidArgumentException->getMessage());
        }

        return null;
    }

    private function renderResult(MarkOldInstallationsResult $result): int
    {
        if ($result->dryRun) {
            $this->io->note(sprintf('Dry-run mode: %d stale installation(s) found.', count($result->staleInstallations)));

            foreach ($result->staleInstallations as $staleInstallation) {
                $this->io->text(sprintf(
                    '  - installation %s, created at %s',
                    $staleInstallation->getId()->toRfc4122(),
                    $staleInstallation->getCreatedAt()->toAtomString()
                ));
            }

            $this->io->newLine();
            $this->io->note('No changes were made. Remove --dry-run to mark installations.');

            return 0;
        }

        if (0 === $result->processedCount) {
            $this->io->success('No stale installations found.');

            return 0;
        }

        $this->io->success(sprintf('Processed %d stale installation(s)', $result->processedCount));

        return $result->processedCount;
    }
}
