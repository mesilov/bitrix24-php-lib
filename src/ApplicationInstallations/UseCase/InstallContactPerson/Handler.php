<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\InstallContactPerson;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private PhoneNumberUtil $phoneNumberUtil,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ContactPerson.InstallContactPerson.start', [
            'applicationInstallationId' => $command->applicationInstallationId,
            'bitrix24UserId' => $command->bitrix24UserId,
            'bitrix24PartnerId' => $command->bitrix24PartnerId?->toRfc4122() ?? '',
        ]);

        $createdContactPersonId = '';

        try {
            if (null !== $command->mobilePhoneNumber) {
                try {
                    $this->guardMobilePhoneNumber($command->mobilePhoneNumber);
                } catch (InvalidArgumentException) {
                    // Ошибка уже залогирована внутри гарда.
                    // Прерываем создание контакта, но не останавливаем установку приложения.
                    return;
                }
            }

            /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
            $applicationInstallation = $this->applicationInstallationRepository->getById($command->applicationInstallationId);

            $uuidV7 = Uuid::v7();

            $contactPerson = new ContactPerson(
                $uuidV7,
                ContactPersonStatus::active,
                $command->fullName,
                $command->email,
                null,
                $command->mobilePhoneNumber,
                null,
                $command->comment,
                $command->externalId,
                $command->bitrix24UserId,
                $command->bitrix24PartnerId,
                $command->userAgentInfo,
                true
            );

            $this->contactPersonRepository->save($contactPerson);

            if ($contactPerson->isPartner()) {
                $applicationInstallation->linkBitrix24PartnerContactPerson($uuidV7);
            } else {
                $applicationInstallation->linkContactPerson($uuidV7);
            }

            $this->applicationInstallationRepository->save($applicationInstallation);

            $this->flusher->flush($contactPerson, $applicationInstallation);

            $createdContactPersonId = $uuidV7->toRfc4122();
        } catch (ApplicationInstallationNotFoundException $applicationInstallationNotFoundException) {
            $this->logger->warning('ContactPerson.InstallContactPerson.applicationInstallationNotFound', [
                'applicationInstallationId' => $command->applicationInstallationId,
                'message' => $applicationInstallationNotFoundException->getMessage(),
            ]);

            throw $applicationInstallationNotFoundException;
        } finally {
            $this->logger->info('ContactPerson.InstallContactPerson.finish', [
                'applicationInstallationId' => $command->applicationInstallationId,
                'bitrix24UserId' => $command->bitrix24UserId,
                'bitrix24PartnerId' => $command->bitrix24PartnerId?->toRfc4122() ?? '',
                'contact_person_id' => $createdContactPersonId,
            ]);
        }
    }

    private function guardMobilePhoneNumber(PhoneNumber $mobilePhoneNumber): void
    {
        if (!$this->phoneNumberUtil->isValidNumber($mobilePhoneNumber)) {
            $this->logger->warning('ContactPerson.InstallContactPerson.InvalidMobilePhoneNumber', [
                'mobilePhoneNumber' => (string) $mobilePhoneNumber,
            ]);

            throw new InvalidArgumentException('Invalid mobile phone number.');
        }

        if (PhoneNumberType::MOBILE !== $this->phoneNumberUtil->getNumberType($mobilePhoneNumber)) {
            $this->logger->warning('ContactPerson.InstallContactPerson.MobilePhoneNumberMustBeMobile', [
                'mobilePhoneNumber' => (string) $mobilePhoneNumber,
            ]);

            throw new InvalidArgumentException('Phone number must be mobile.');
        }
    }
}
