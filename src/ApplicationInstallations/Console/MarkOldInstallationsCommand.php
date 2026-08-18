<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Console;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkAsNeedReinstall\Command as MarkAsNeedReinstallCommand;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkAsNeedReinstall\Handler;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'bitrix24:installations:mark-old',
    description: 'Mark stale pending installations as needReinstall'
)]
class MarkOldInstallationsCommand extends Command
{
    public const DEFAULT_TTL = 3600;

    private ?SymfonyStyle $io = null;

    public function __construct(
        private readonly ApplicationInstallationRepository $applicationInstallationRepository,
        private readonly Handler $markAsNeedReinstallHandler
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
            ->setHelp(
                <<<'HELP'
The <info>bitrix24:installations:mark-old</info> command finds pending installations
in status "new" older than the given TTL and marks them as "needReinstall".

<comment>Mark installations older than 1 hour (default):</comment>
  <info>php bin/console bitrix24:installations:mark-old</info>

<comment>Mark installations older than 30 minutes:</comment>
  <info>php bin/console bitrix24:installations:mark-old 1800</info>
HELP
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $ttl = (int) $input->getArgument('ttl');
        if ($ttl < 0) {
            $this->io->error('TTL in seconds must be a non-negative integer.');

            return Command::FAILURE;
        }

        $olderThan = new CarbonImmutable();
        $olderThan = $olderThan->subSeconds($ttl);

        $staleInstallations = $this->applicationInstallationRepository->findStaleInstallations(
            ApplicationInstallationStatus::new,
            $olderThan
        );

        return $this->processStaleInstallations($staleInstallations, $ttl);
    }

    private function processStaleInstallations(array $staleInstallations, int $ttl): int
    {
        if ([] === $staleInstallations) {
            $this->io->success('No stale installations found.');

            return Command::SUCCESS;
        }

        $comment = sprintf('installation timed out without ONAPPINSTALL, TTL = %d seconds', $ttl);

        $markedIds = [];
        $failedIds = [];

        foreach ($staleInstallations as $staleInstallation) {
            $installationId = $staleInstallation->getId();

            try {
                $this->markAsNeedReinstallHandler->handle(
                    new MarkAsNeedReinstallCommand($installationId, $comment)
                );

                $markedIds[] = $installationId;
            } catch (LogicException) {
                // Installation changed status concurrently (e.g. ONAPPINSTALL arrived) — skip it.
                $failedIds[] = $installationId;
            }
        }

        return $this->renderResult($markedIds, $failedIds);
    }

    /**
     * @param Uuid[] $markedIds
     * @param Uuid[] $failedIds
     */
    private function renderResult(array $markedIds, array $failedIds): int
    {
        $this->io->success(sprintf('Marked %d installation(s) as needReinstall:', count($markedIds)));

        foreach ($markedIds as $installationId) {
            $this->io->text(sprintf('  - installation %s', $installationId->toRfc4122()));
        }

        if ([] !== $failedIds) {
            $this->io->warning(sprintf('Skipped %d installation(s) changed status concurrently:', count($failedIds)));

            foreach ($failedIds as $installationId) {
                $this->io->text(sprintf('  - installation %s', $installationId->toRfc4122()));
            }
        }

        return Command::SUCCESS;
    }
}
