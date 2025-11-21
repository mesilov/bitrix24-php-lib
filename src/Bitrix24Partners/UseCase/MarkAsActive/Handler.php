<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\MarkAsActive;

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
        $this->logger->info('Bitrix24Partners.MarkAsActive.start', [
            'partner_id' => $command->id->toRfc4122(),
        ]);

        $partner = $this->bitrix24PartnerRepository->getById($command->id);
        $partner->markAsActive($command->comment);

        $this->bitrix24PartnerRepository->save($partner);
        $this->flusher->flush($partner);

        $this->logger->info('Bitrix24Partners.MarkAsActive.finish', [
            'partner_id' => $command->id->toRfc4122(),
        ]);
    }
}
