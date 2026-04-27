<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Create;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Exceptions\Bitrix24PartnerNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
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
        private Bitrix24PartnerRepositoryInterface $bitrix24PartnerRepository,
        private Flusher                            $flusher,
        private PhoneNumberUtil                    $phoneNumberUtil,
        private LoggerInterface                    $logger
    )
    {
    }

    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Partners.Create.start', [
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|Bitrix24PartnerInterface $activePartner */
            $activePartner = $this->bitrix24PartnerRepository->findByBitrix24PartnerNumber($command->bitrix24PartnerNumber);

            if (null !== $activePartner) {
                throw new InvalidArgumentException('partner with this number already exists');
            }

            if (null !== $command->phone) {
                $this->guardMobilePhoneNumber($command->phone);
            }

            $bitrix24Partner = new Bitrix24Partner(
                Uuid::v7(),
                $command->title,
                $command->bitrix24PartnerNumber,
                $command->site,
                $command->phone,
                $command->email,
                $command->openLineId,
                $command->externalId
            );

            $this->bitrix24PartnerRepository->save($bitrix24Partner);
            $this->flusher->flush($bitrix24Partner);

            $this->logger->info('Bitrix24Partners.Create.success', [
                'partner_id' => $bitrix24Partner->getId()->toRfc4122(),
                'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
            ]);

        } catch (Bitrix24PartnerNotFoundException $exception) {
            $this->logger->warning('Bitrix24Partners.Create.failed', [
                'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->logger->info('Bitrix24Partners.Create.finish');
        }
    }

    private function guardMobilePhoneNumber(PhoneNumber $phoneNumber): void
    {
        if (!$this->phoneNumberUtil->isValidNumber($phoneNumber)) {
            $this->logger->warning('ContactPerson.Create.InvalidMobilePhoneNumber', [
                'mobilePhoneNumber' => (string)$phoneNumber,
            ]);

            throw new InvalidArgumentException('Invalid mobile phone number.');
        }

        if (PhoneNumberType::MOBILE !== $this->phoneNumberUtil->getNumberType($phoneNumber)) {
            $this->logger->warning('ContactPerson.Create.MobilePhoneNumberMustBeMobile', [
                'mobilePhoneNumber' => (string)$phoneNumber,
            ]);

            throw new InvalidArgumentException('Phone number must be mobile.');
        }
    }
}
