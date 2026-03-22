<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberType;
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
        $this->logger->info('ContactPerson.ChangeProfile.start', [
            'contactPersonId' => $command->contactPersonId,
            'fullName' => (string) $command->fullName,
            'email' => $command->email,
            'mobilePhoneNumber' => (string) $command->mobilePhoneNumber,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
            $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

            if (!$command->fullName->equal($contactPerson->getFullName())) {
                $contactPerson->changeFullName($command->fullName);
            }

            if ($command->email !== $contactPerson->getEmail()) {
                $contactPerson->changeEmail($command->email);
            }

            $this->guardMobilePhoneNumber($command->mobilePhoneNumber);
            if (!$command->mobilePhoneNumber->equals($contactPerson->getMobilePhone())) {
                $contactPerson->changeMobilePhone($command->mobilePhoneNumber);
            }

            $this->contactPersonRepository->save($contactPerson);
            $this->flusher->flush($contactPerson);

            $this->logger->info('ContactPerson.ChangeProfile.finish', [
                'contactPersonId' => $contactPerson->getId()->toRfc4122(),
                'updatedFields' => [
                    'fullName' => (string) $command->fullName,
                    'email' => $command->email,
                    'mobilePhoneNumber' => (string) $command->mobilePhoneNumber,
                ],
            ]);
        } catch (ContactPersonNotFoundException $contactPersonNotFoundException) {
            $this->logger->warning('ContactPerson.ChangeProfile.contactPersonNotFound', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
                'message' => $contactPersonNotFoundException->getMessage(),
            ]);

            throw $contactPersonNotFoundException;
        } finally {
            $this->logger->info('ContactPerson.ChangeProfile.finish', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
            ]);
        }
    }

    private function guardMobilePhoneNumber(PhoneNumber $mobilePhoneNumber): void
    {
        if (!$this->phoneNumberUtil->isValidNumber($mobilePhoneNumber)) {
            $this->logger->warning('ContactPerson.ChangeProfile.InvalidMobilePhoneNumber', [
                'mobilePhoneNumber' => (string) $mobilePhoneNumber,
            ]);

            throw new InvalidArgumentException('Invalid mobile phone number.');
        }

        if (PhoneNumberType::MOBILE !== $this->phoneNumberUtil->getNumberType($mobilePhoneNumber)) {
            $this->logger->warning('ContactPerson.ChangeProfile.MobilePhoneNumberMustBeMobile', [
                'mobilePhoneNumber' => (string) $mobilePhoneNumber,
            ]);

            throw new InvalidArgumentException('Phone number must be mobile.');
        }
    }
}
