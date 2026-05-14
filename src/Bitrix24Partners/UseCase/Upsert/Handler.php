<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Upsert;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

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
        $this->logger->info('Bitrix24Partners.Upsert.start', [
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|Bitrix24PartnerInterface $existingPartner */
            $existingPartner = $this->bitrix24PartnerRepository->findByBitrix24PartnerNumber($command->bitrix24PartnerNumber);

            if (null !== $command->phone) {
                $this->guardMobilePhoneNumber($command->phone);
            }

            if (null === $existingPartner) {
                $this->create($command);

                return;
            }

            \assert($existingPartner instanceof Bitrix24Partner);

            $this->updateIfNeeded($command, $existingPartner);
        } finally {
            $this->logger->info('Bitrix24Partners.Upsert.finish', [
                'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
            ]);
        }
    }

    private function create(Command $command): void
    {
        $partner = new Bitrix24Partner(
            Uuid::v7(),
            $command->title,
            $command->bitrix24PartnerNumber,
            $command->site,
            $command->phone,
            $command->email,
            $command->openLineId,
            $command->externalId,
            $command->logoUrl
        );

        $this->bitrix24PartnerRepository->save($partner);
        $this->flusher->flush($partner);

        $this->logger->info('Bitrix24Partners.Upsert.created', [
            'partner_id' => $partner->getId()->toRfc4122(),
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);
    }

    private function updateIfNeeded(Command $command, Bitrix24Partner $existingPartner): void
    {
        $tempPartner = new Bitrix24Partner(
            Uuid::v7(),
            $command->title,
            $command->bitrix24PartnerNumber,
            $command->site,
            $command->phone,
            $command->email,
            $command->openLineId,
            $command->externalId,
            $command->logoUrl
        );

        if ($existingPartner->equals($tempPartner)) {
            $this->logger->info('Bitrix24Partners.Upsert.skipped', [
                'partner_id' => $existingPartner->getId()->toRfc4122(),
                'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
                'reason' => 'no changes',
            ]);

            return;
        }

        $this->applyChanges($command, $existingPartner);

        $this->bitrix24PartnerRepository->save($existingPartner);
        $this->flusher->flush($existingPartner);

        $this->logger->info('Bitrix24Partners.Upsert.updated', [
            'partner_id' => $existingPartner->getId()->toRfc4122(),
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);
    }

    private function applyChanges(Command $command, Bitrix24PartnerInterface $partner): void
    {
        if ($command->title !== $partner->getTitle()) {
            $partner->changeTitle($command->title);
        }

        if ($command->site !== $partner->getSite()) {
            $partner->changeSite($command->site);
        }

        $this->guardPhoneChange($command->phone, $partner->getPhone());
        if (!$this->phonesEqual($command->phone, $partner->getPhone())) {
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
    }

    private function phonesEqual(?PhoneNumber $a, ?PhoneNumber $b): bool
    {
        if (null === $a && null === $b) {
            return true;
        }

        if (null !== $a && null !== $b) {
            return $a->equals($b);
        }

        return false;
    }

    private function guardPhoneChange(?PhoneNumber $newPhone, ?PhoneNumber $currentPhone): void
    {
        if (null === $newPhone || null !== $currentPhone) {
            return;
        }

        $this->guardMobilePhoneNumber($newPhone);
    }

    private function guardMobilePhoneNumber(PhoneNumber $phoneNumber): void
    {
        if (!$this->phoneNumberUtil->isValidNumber($phoneNumber)) {
            $this->logger->warning('Bitrix24Partners.Upsert.InvalidMobilePhoneNumber', [
                'mobilePhoneNumber' => (string) $phoneNumber,
            ]);

            throw new InvalidArgumentException('Invalid mobile phone number.');
        }
    }
}
