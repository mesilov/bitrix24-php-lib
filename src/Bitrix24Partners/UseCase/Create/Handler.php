<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Create;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
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
        $this->logger->info('Bitrix24Partners.Create.start', [
            'title' => $command->title,
            'bitrix24_partner_id' => $command->bitrix24PartnerId,
        ]);

        $bitrix24Partner = new Bitrix24Partner(
            $command->title,
            $command->bitrix24PartnerId,
            $command->site,
            $command->phone,
            $command->email,
            $command->openLineId,
            $command->externalId
        );

        $this->bitrix24PartnerRepository->save($bitrix24Partner);
        $this->flusher->flush($bitrix24Partner);

        $this->logger->info('Bitrix24Partners.Create.finish', [
            'partner_id' => $bitrix24Partner->getId()->toRfc4122(),
            'title' => $command->title,
        ]);
    }
}
