<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkEmailAsVerified;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ContactPerson.MarkEmailVerification.start', [
            'contactPersonId' => $command->contactPersonId->toRfc4122(),
            'email' => $command->email,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
            $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);
        } catch (ContactPersonNotFoundException $contactPersonNotFoundException) {
            $this->logger->warning('ContactPerson.MarkEmailVerification.contactPersonNotFound', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
            ]);

            throw $contactPersonNotFoundException;
        }

        $actualEmail = $contactPerson->getEmail();
        if (null == $actualEmail) {
            $this->logger->warning('ContactPerson.MarkEmailVerification.currentEmailIsNull', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
                'actualEmail' => null,
                'expectedEmail' => $command->email,
            ]);

            return;
        }

        if (mb_strtolower($actualEmail) === mb_strtolower($command->email)) {
            $contactPerson->markEmailAsVerified($command->emailVerifiedAt);
            $this->contactPersonRepository->save($contactPerson);
            $this->flusher->flush($contactPerson);
        } else {
            $this->logger->warning('ContactPerson.MarkEmailVerification.emailMismatch', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
                'actualEmail' => $actualEmail,
                'expectedEmail' => $command->email,
            ]);
        }

        $this->logger->info('ContactPerson.MarkEmailVerification.finish', [
            'contactPersonId' => $contactPerson->getId()->toRfc4122(),
            'emailVerifiedAt' => $contactPerson->getEmailVerifiedAt()?->toIso8601String(),
        ]);
    }
}
