<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\UnlinkContactPerson;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ContactPerson.UnlinkContactPerson.start', [
            'contactPersonId' => $command->contactPersonId,
            'applicationInstallationId' => $command->applicationInstallationId,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
            $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

            /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
            $applicationInstallation = $this->applicationInstallationRepository->getById($command->applicationInstallationId);

            $entitiesToFlush = [];
            if ($contactPerson->isPartner()) {
                if (null !== $applicationInstallation->getBitrix24PartnerContactPersonId()) {
                    $applicationInstallation->unlinkBitrix24PartnerContactPerson();
                    $this->applicationInstallationRepository->save($applicationInstallation);
                    $entitiesToFlush[] = $applicationInstallation;
                }
            } elseif (null !== $applicationInstallation->getContactPersonId()) {
                $applicationInstallation->unlinkContactPerson();
                $this->applicationInstallationRepository->save($applicationInstallation);
                $entitiesToFlush[] = $applicationInstallation;
            } else {
                $this->logger->warning('ContactPerson.UnlinkContactPerson.alreadyUnlinked', [
                    'contactPersonId' => $command->contactPersonId,
                    'applicationInstallationId' => $command->applicationInstallationId,
                ]);
            }

            $contactPerson->markAsDeleted($command->comment);
            $this->contactPersonRepository->save($contactPerson);
            $entitiesToFlush[] = $contactPerson;

            $this->flusher->flush(...$entitiesToFlush);
        } catch (ApplicationInstallationNotFoundException|ContactPersonNotFoundException $e) {
            $this->logger->warning('ContactPerson.UnlinkContactPerson.notFound', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->logger->info('ContactPerson.UnlinkContactPerson.finish', [
                'contactPersonId' => $command->contactPersonId,
                'applicationInstallationId' => $command->applicationInstallationId,
            ]);
        }
    }
}
