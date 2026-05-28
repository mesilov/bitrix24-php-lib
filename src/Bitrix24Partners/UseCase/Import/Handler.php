<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24PartnerRepository $bitrix24PartnerRepository,
        private Flusher $flusher,
        private PhoneNumberUtil $phoneNumberUtil,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Partners.Import.start', [
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|Bitrix24PartnerInterface $existingPartner */
            $existingPartner = $this->bitrix24PartnerRepository->findByBitrix24PartnerNumber(
                $command->bitrix24PartnerNumber,
                withDeleted: true
            );

            if (null !== $command->phone) {
                $this->guardMobilePhoneNumber($command->phone);
            }

            if (null === $existingPartner) {
                $this->create($command);

                return;
            }

            if (Bitrix24PartnerStatus::deleted === $existingPartner->getStatus()) {
                $this->logger->warning('Bitrix24Partners.Import.skipped', [
                    'partner_id' => $existingPartner->getId()->toRfc4122(),
                    'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
                    'reason' => 'partner is deleted',
                ]);

                return;
            }

            if (!$existingPartner instanceof Bitrix24Partner) {
                throw new \LogicException(sprintf(
                    'Expected instance of %s, got %s',
                    Bitrix24Partner::class,
                    $existingPartner::class
                ));
            }

            $this->updateIfNeeded($command, $existingPartner);
        } finally {
            $this->logger->info('Bitrix24Partners.Import.finish', [
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
            null,
            null,
            $command->logoUrl
        );

        $this->bitrix24PartnerRepository->save($partner);
        $this->flusher->flush($partner);

        $this->logger->info('Bitrix24Partners.Import.created', [
            'partner_id' => $partner->getId()->toRfc4122(),
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);
    }

    private function updateIfNeeded(Command $command, Bitrix24Partner $existingPartner): void
    {
        $isUpdated = false;

        if ($command->title !== $existingPartner->getTitle()) {
            $existingPartner->changeTitle($command->title);
            $isUpdated = true;
        }

        if ($command->site !== $existingPartner->getSite()) {
            $existingPartner->changeSite($command->site);
            $isUpdated = true;
        }

        if (!$this->phonesEqual($command->phone, $existingPartner->getPhone())) {
            $this->guardPhoneChange($command->phone, $existingPartner->getPhone());
            $existingPartner->changePhone($command->phone);
            $isUpdated = true;
        }

        if ($command->email !== $existingPartner->getEmail()) {
            $existingPartner->changeEmail($command->email);
            $isUpdated = true;
        }

        if ($command->logoUrl !== $existingPartner->getLogoUrl()) {
            $existingPartner->changeLogoUrl($command->logoUrl);
            $isUpdated = true;
        }

        if (!$isUpdated) {
            $this->logger->info('Bitrix24Partners.Import.skipped', [
                'partner_id' => $existingPartner->getId()->toRfc4122(),
                'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
                'reason' => 'no changes',
            ]);

            return;
        }

        $this->bitrix24PartnerRepository->save($existingPartner);
        $this->flusher->flush($existingPartner);

        $this->logger->info('Bitrix24Partners.Import.updated', [
            'partner_id' => $existingPartner->getId()->toRfc4122(),
            'bitrix24_partner_id' => $command->bitrix24PartnerNumber,
        ]);
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
            $this->logger->warning('Bitrix24Partners.Import.InvalidMobilePhoneNumber', [
                'mobilePhoneNumber' => (string) $phoneNumber,
            ]);

            throw new InvalidArgumentException('Invalid mobile phone number.');
        }
    }
}
