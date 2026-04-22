<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Update;

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
                $partner->setTitle($command->title);
            }

            if ($command->site !== $partner->getSite()) {
                $partner->setSite($command->site);
            }

            if (null !== $command->phone && !$command->phone->equals($partner->getPhone())) {
                $partner->setPhone($command->phone);
            }

            if ($command->email !== $partner->getEmail()) {
                $partner->setEmail($command->email);
            }

            if ($command->openLineId !== $partner->getOpenLineId()) {
                $partner->setOpenLineId($command->openLineId);
            }

            if ($command->externalId !== $partner->getExternalId()) {
                $partner->setExternalId($command->externalId);
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
}
