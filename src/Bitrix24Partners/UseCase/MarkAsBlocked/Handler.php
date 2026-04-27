<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\MarkAsBlocked;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Exceptions\Bitrix24PartnerNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
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
        $this->logger->info('Bitrix24Partners.MarkAsBlocked.start', [
            'partner_id' => $command->id->toRfc4122(),
        ]);

        try {
            /** @var AggregateRootEventsEmitterInterface|Bitrix24PartnerInterface $partner */
            $partner = $this->bitrix24PartnerRepository->getById($command->id);
            $partner->markAsBlocked($command->comment);

            $this->bitrix24PartnerRepository->save($partner);
            $this->flusher->flush($partner);

        } catch (Bitrix24PartnerNotFoundException $exception) {
            $this->logger->warning('Bitrix24Partners.MarkAsBlocked.partnerNotFound', [
                'id' => $command->id->toRfc4122(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->logger->info('Bitrix24Partners.MarkAsBlocked.finish', [
                'partner_id' => $command->id->toRfc4122(),
            ]);
        }
    }
}
