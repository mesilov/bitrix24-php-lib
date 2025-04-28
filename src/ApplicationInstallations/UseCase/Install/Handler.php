<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepository         $bitrix24AccountRepository,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher                           $flusher,
        private LoggerInterface                   $logger
    )
    {
    }

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.InstallStart.start', [
            (string)$command
        ]);

        $accountsCount = 0;

        /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeApplicationInstallation */
        $activeApplicationInstallation = $this->getActiveApplicationInstallation();

        if (!empty($activeApplicationInstallation)) {

            //Решили 13 апреля расширить контракт установки и добавить получения токена
            $activeApplicationToken = $activeApplicationInstallation->getApplicationToken();

            $oldBitrix24AccountId = $activeApplicationInstallation->getBitrix24AccountId();
            $oldBitrix24Account = $this->bitrix24AccountRepository->getById($oldBitrix24AccountId);
            /** @var AggregateRootEventsEmitterInterface[]|Bitrix24AccountInterface[] $accounts */
            $accounts = $this->bitrix24AccountRepository->findByMemberId($oldBitrix24Account->getMemberId());
            $accountsCount = count($accounts);
            foreach ($accounts as $account) {
                //Сюда пробрасываем токен полученный выше
                $account->applicationUninstalled($activeApplicationToken);
                $this->bitrix24AccountRepository->save($account);
            }

            $activeApplicationInstallation->applicationUninstalled();

            $this->flusher->flush(...$accounts);
        }

        $bitrix24AccountId = Uuid::v7();
        $applicationInstallationId = Uuid::v7();

        $bitrix24Account = new Bitrix24Account(
            $bitrix24AccountId,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domain->value,
            $command->authToken,
            $command->applicationVersion,
            $command->applicationScope,
            true
        );


        $bitrix24Account->applicationInstalled($command->applicationToken);

        $applicationInstallation = new ApplicationInstallation(
            $applicationInstallationId,
            $bitrix24AccountId,
            $command->applicationStatus,
            $command->portalLicenseFamily,
            $command->portalUsersCount,
            $command->contactPersonId,
            $command->bitrix24PartnerContactPersonId,
            $command->bitrix24PartnerId,
            $command->externalId,
            $command->comment,
            true
        );

        $applicationInstallation->applicationInstalled();

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation,$bitrix24Account);

        $this->logger->info(
            'ApplicationInstallations.InstallStart.Finish',
            [
                'applicationId' => $applicationInstallationId,
                'bitrix24AccountId' => $bitrix24AccountId,
                'accountsUninstalledCount' => $accountsCount,
            ]
        );
    }
    private function getActiveApplicationInstallation(): ApplicationInstallationInterface|null
    {
        $activeApplicationInstallations = $this->applicationInstallationRepository->findActiveApplicationInstallations();

        if (count($activeApplicationInstallations) > 1) {
            throw new \InvalidArgumentException(
                'multiple application installations with active or new status'
            );
        }

        return $activeApplicationInstallations[0] ?? null;
    }
}
