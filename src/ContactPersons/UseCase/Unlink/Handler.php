<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\Unlink;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
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
        $this->logger->info('ContactPerson.UninstallContactPerson.start', [
            'contactPersonId' => $command->contactPersonId,
        ]);

        /** @var AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
        $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

        /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
        $applicationInstallation = $this->applicationInstallationRepository->getCurrent();

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
        }

        $contactPerson->markAsDeleted($command->comment);
        $this->contactPersonRepository->save($contactPerson);
        $entitiesToFlush[] = $contactPerson;

        $this->flusher->flush(...$entitiesToFlush);

        $this->logger->info('ContactPerson.UninstallContactPerson.finish', [
            'contact_person_id' => $command->contactPersonId,
        ]);
    }
}
