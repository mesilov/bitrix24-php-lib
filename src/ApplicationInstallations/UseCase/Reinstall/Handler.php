<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Reinstall;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepository $bitrix24AccountRepository,
        private ApplicationInstallationRepository $applicationInstallationRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallations.InstallStart.start', [
            'id' => $command->uuid->toRfc4122(),
        ]);

        /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeApplicationInstallation */
        $activeApplicationInstallation = $this->getActiveApplicationInstallation();

        /*
            В документации написано, что нужно деактивировать аккаунтЫ связанные с приложением. Но у нас связь у приложения идет только с 1 аккаунтом.
            Отсюда вопрос у нас аккаунт у приложения один ? Или если несколько то как их получить ?
        */

        // Еще вопрос написано деактивация приложения и аккаунтов связанных с приложением это soft delete ?

        if (!empty($activeApplicationInstallation)) {
            $bitrix24AccountId = $activeApplicationInstallation->getBitrix24AccountId();
            $oldBitrix24Account = $this->bitrix24AccountRepository->getById($bitrix24AccountId);
            $activeApplicationInstallation->applicationUninstalled();
            $this->applicationInstallationRepository->save($activeApplicationInstallation);
            $this->flusher->flush($activeApplicationInstallation);
            $oldBitrix24Account->applicationUninstalled($command->applicationToken);
            $this->bitrix24AccountRepository->save($oldBitrix24Account);
            $this->flusher->flush($oldBitrix24Account);
        }

        // Тут возникает вопрос. Для деинсталяции(если деактивация означает это) нам нужно токен прокидывать в команду, мне кажется бредово.
        // Так как мы ходим запустить хендлер с параметрами нового приложения и чтобы там под капотом все что нужно деактивировалось, а что нужно установилось.
        // Может добавить метод получения токена ?  Или он уже должен быть и мы просто проморгали ?

        // Ниже пока что можно не смотреть
        $bitrix24Account = new Bitrix24Account(
            $command->bitrix24AccountUuid,
            $command->bitrix24UserId,
            $command->isBitrix24UserAdmin,
            $command->memberId,
            $command->domain->value,
            $command->bitrix24AccountStatus,
            $command->authToken,
            new CarbonImmutable(),
            new CarbonImmutable(),
            $command->applicationVersion,
            $command->applicationScope,
            true
        );

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account->applicationInstalled($applicationToken);
        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $applicationInstallation = new ApplicationInstallation(
            $command->uuid,
            $command->applicationInstallationStatus,
            new CarbonImmutable(),
            new CarbonImmutable(),
            $command->bitrix24AccountId,
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

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation);

        $applicationInstallation->applicationInstalled();

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush($applicationInstallation);

        $this->logger->info(
            'Bitrix24Accounts.InstallStart.Finish',
            [
                'id' => $command->uuid->toRfc4122(),
            ]
        );
    }

    private function getActiveApplicationInstallation(): ApplicationInstallationInterface
    {
        $activeApplicationInstallations = $this->applicationInstallationRepository->findActiveApplicationInstallations();

        if (!([] === $activeApplicationInstallations) && count($activeApplicationInstallations) > 1) {
            // Тут может добавить исключение для приложения ? По подобию MultipleBitrix24AccountsFoundException
            throw new \InvalidArgumentException(
                'multiple application installations with active or new status'
            );
        }

        return $activeApplicationInstallations[0];
    }
}
