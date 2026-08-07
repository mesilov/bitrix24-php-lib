<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @return ApplicationInstallation[]
     *
     * @throws LogicException
     */
    private function findStaleInstallations(Command $command): array
    {
        $olderThan = new CarbonImmutable();
        $olderThan = $olderThan->subSeconds($command->ttlInSeconds);

        return $this->applicationInstallationRepository->findStaleInstallations(
            ApplicationInstallationStatus::new,
            $olderThan,
            $command->memberId
        );
    }

    /**
     * @throws LogicException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.MarkOldInstallations.start', [
            'ttlInSeconds' => $command->ttlInSeconds,
            'memberId' => $command->memberId,
        ]);

        $staleInstallations = $this->findStaleInstallations($command);

        foreach ($staleInstallations as $staleInstallation) {
            $staleInstallation->markAsNeedReinstall(
                sprintf('installation timed out without ONAPPINSTALL, TTL = %d seconds', $command->ttlInSeconds)
            );

            $this->applicationInstallationRepository->save($staleInstallation);
            $this->flusher->flush($staleInstallation);

            $this->logger->info('ApplicationInstallations.MarkOldInstallations.marked', [
                'installationId' => $staleInstallation->getId()->toRfc4122(),
                'createdAt' => $staleInstallation->getCreatedAt()->toAtomString(),
            ]);
        }

        $this->logger->info('ApplicationInstallations.MarkOldInstallations.finish');
    }
}
