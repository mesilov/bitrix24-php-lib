<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private PhoneNumberUtil $phoneNumberUtil,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        // Format expected mobile phone number to the international E.164 format
        $expectedMobilePhoneE164 = $this->phoneNumberUtil->format($command->phone, PhoneNumberFormat::E164);

        $this->logger->info('ContactPerson.MarkMobilePhoneVerification.start', [
            'contactPersonId' => $command->contactPersonId->toRfc4122(),
            'phone' => $expectedMobilePhoneE164,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
            $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

            $actualPhone = $contactPerson->getMobilePhone();
            if (null == $actualPhone) {
                $this->logger->warning('ContactPerson.MarkMobilePhoneVerification.currentPhoneIsNull', [
                    'contactPersonId' => $command->contactPersonId->toRfc4122(),
                    'actualPhone' => null,
                    'expectedPhone' => $expectedMobilePhoneE164,
                ]);

                return;
            }

            if ($command->phone->equals($actualPhone)) {
                $contactPerson->markMobilePhoneAsVerified($command->phoneVerifiedAt);

                $this->contactPersonRepository->save($contactPerson);
                $this->flusher->flush($contactPerson);
            } else {
                // Format the current mobile phone number to the international E.164 format
                $actualMobilePhoneE164 = $this->phoneNumberUtil->format($actualPhone, PhoneNumberFormat::E164);

                $this->logger->warning('ContactPerson.MarkMobilePhoneVerification.phoneMismatch', [
                    'contactPersonId' => $command->contactPersonId->toRfc4122(),
                    'actualPhone' => $actualMobilePhoneE164,
                    'expectedPhone' => $expectedMobilePhoneE164,
                ]);

                return;
            }
        } catch (ContactPersonNotFoundException $contactPersonNotFoundException) {
            $this->logger->warning('ContactPerson.MarkMobilePhoneVerification.contactPersonNotFound', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
                'message' => $contactPersonNotFoundException->getMessage(),
            ]);

            throw $contactPersonNotFoundException;
        } finally {
            $this->logger->info('ContactPerson.MarkMobilePhoneVerification.finish', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
            ]);
        }
    }
}
