<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

readonly class Workflow
{
    public function __construct(
        private Handler $handler,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private LoggerInterface $logger
    ) {}

    public function run(MarkOldInstallationsConfig $config): MarkOldInstallationsResult
    {
        $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.start', [
            'ttlInSeconds' => $config->ttlInSeconds,
            'memberId' => $config->memberId,
            'dryRun' => $config->dryRun,
        ]);

        if ($config->dryRun) {
            $olderThan = new CarbonImmutable();
            $olderThan = $olderThan->subSeconds($config->ttlInSeconds);

            $staleInstallations = $this->applicationInstallationRepository->findStaleInstallations(
                ApplicationInstallationStatus::new,
                $olderThan,
                $config->memberId
            );

            $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.dryRun', [
                'foundCount' => count($staleInstallations),
            ]);

            return new MarkOldInstallationsResult(true, $staleInstallations);
        }

        $command = new Command($config->ttlInSeconds, $config->memberId);
        $this->handler->handle($command);

        $this->logger->info('ApplicationInstallations.MarkOldInstallations.Workflow.finish');

        return new MarkOldInstallationsResult(false);
    }
}
