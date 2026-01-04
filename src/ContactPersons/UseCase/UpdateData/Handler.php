<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\UpdateData;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use libphonenumber\PhoneNumber;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ContactPerson.UpdateData.start', [
            'contactPersonId' => $command->contactPersonId,
            'fullName' => $command->fullName?->name ?? null,
            'email' => $command->email,
            'mobilePhoneNumber' => $command->mobilePhoneNumber?->__toString() ?? null,
            'externalId' => $command->externalId,
            'bitrix24PartnerId' => $command->bitrix24PartnerId?->toRfc4122() ?? null,
        ]);

        /** @var AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
        $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

        if ($command->fullName instanceof FullName) {
            $contactPerson->changeFullName($command->fullName);
        }

        if (null !== $command->email) {
            $contactPerson->changeEmail($command->email);
        }

        if ($command->mobilePhoneNumber instanceof PhoneNumber) {
            $contactPerson->changeMobilePhone($command->mobilePhoneNumber);
        }

        if (null !== $command->externalId) {
            $contactPerson->setExternalId($command->externalId);
        }

        if ($command->bitrix24PartnerId instanceof Uuid) {
            $contactPerson->setBitrix24PartnerId($command->bitrix24PartnerId);
        }

        $this->contactPersonRepository->save($contactPerson);
        $this->flusher->flush($contactPerson);

        $this->logger->info('ContactPerson.UpdateData.finish', [
            'contactPersonId' => $contactPerson->getId()->toRfc4122(),
            'updatedFields' => [
                'fullName' => $command->fullName?->name ?? null,
                'email' => $command->email,
                'mobilePhoneNumber' => $command->mobilePhoneNumber?->__toString() ?? null,
                'externalId' => $command->externalId,
                'bitrix24PartnerId' => $command->bitrix24PartnerId?->toRfc4122() ?? null,
            ],
        ]);
    }
}
