<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Update;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24PartnerRepositoryInterface $bitrix24PartnerRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Partners.Update.start', [
            'partner_id' => $command->id->toRfc4122(),
        ]);

        $partner = $this->bitrix24PartnerRepository->getById($command->id);

        if (null !== $command->title) {
            $partner->setTitle($command->title);
        }

        if (null !== $command->site) {
            $partner->setSite($command->site);
        }

        if (null !== $command->phone) {
            $partner->setPhone($command->phone);
        }

        if (null !== $command->email) {
            $partner->setEmail($command->email);
        }

        if (null !== $command->bitrix24PartnerId) {
            $partner->setBitrix24PartnerId($command->bitrix24PartnerId);
        }

        if (null !== $command->openLineId) {
            $partner->setOpenLineId($command->openLineId);
        }

        if (null !== $command->externalId) {
            $partner->setExternalId($command->externalId);
        }

        $this->bitrix24PartnerRepository->save($partner);
        $this->flusher->flush($partner);

        $this->logger->info('Bitrix24Partners.Update.finish', [
            'partner_id' => $command->id->toRfc4122(),
        ]);
    }
}
