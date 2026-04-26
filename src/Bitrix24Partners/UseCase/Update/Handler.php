<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Update;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Exceptions\Bitrix24PartnerNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
readonly class Handler
{
    public function __construct(
        private Bitrix24PartnerRepositoryInterface $bitrix24PartnerRepository,
        private Flusher $flusher,
        private PhoneNumberUtil $phoneNumberUtil,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Partners.Update.start', [
            'id' => $command->id->toRfc4122(),
            'title' => $command->title,
            'site' => $command->site,
            'phone' => (string) $command->phone,
            'email' => $command->email,
            'openLineId' => $command->openLineId,
            'externalId' => $command->externalId,
            'logoUrl' => $command->logoUrl,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|Bitrix24PartnerInterface $partner */
            $partner = $this->bitrix24PartnerRepository->getById($command->id);

            if ($command->title !== $partner->getTitle()) {
                $partner->changeTitle($command->title);
            }

            if ($command->site !== $partner->getSite()) {
                $partner->changeSite($command->site);
            }

            $this->guardMobilePhoneNumber($command->phone);
            if (!$command->phone->equals($partner->getPhone())) {
                $partner->changePhone($command->phone);
            }

            if ($command->email !== $partner->getEmail()) {
                $partner->changeEmail($command->email);
            }

            if ($command->openLineId !== $partner->getOpenLineId()) {
                $partner->changeOpenLineId($command->openLineId);
            }

            if ($command->externalId !== $partner->getExternalId()) {
                $partner->changeExternalId($command->externalId);
            }

            if ($command->logoUrl !== $partner->getLogoUrl()) {
                $partner->changeLogoUrl($command->logoUrl);
            }

            $this->bitrix24PartnerRepository->save($partner);
            $this->flusher->flush($partner);

            $this->logger->info('Bitrix24Partners.Update.success', [
                'id' => $partner->getId()->toRfc4122(),
                'updatedFields' => [
                    'title' => $command->title,
                    'site' => $command->site,
                    'phone' => (string) $command->phone,
                    'email' => $command->email,
                    'openLineId' => $command->openLineId,
                    'externalId' => $command->externalId,
                    'logoUrl' => $command->logoUrl,
                ],
            ]);
        } catch (Bitrix24PartnerNotFoundException $exception) {
            $this->logger->warning('Bitrix24Partners.Update.partnerNotFound', [
                'id' => $command->id->toRfc4122(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->logger->info('Bitrix24Partners.Update.finish', [
                'id' => $command->id->toRfc4122(),
            ]);
        }
    }

    private function guardMobilePhoneNumber(PhoneNumber $phoneNumber): void
    {
        if (!$this->phoneNumberUtil->isValidNumber($phoneNumber)) {
            $this->logger->warning('ContactPerson.ChangeProfile.InvalidMobilePhoneNumber', [
                'mobilePhoneNumber' => (string) $phoneNumber,
            ]);

            throw new InvalidArgumentException('Invalid mobile phone number.');
        }

        if (PhoneNumberType::MOBILE !== $this->phoneNumberUtil->getNumberType($phoneNumber)) {
            $this->logger->warning('ContactPerson.ChangeProfile.MobilePhoneNumberMustBeMobile', [
                'mobilePhoneNumber' => (string) $phoneNumber,
            ]);

            throw new InvalidArgumentException('Phone number must be mobile.');
        }
    }
}
