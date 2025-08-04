<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

/**
 * Обработчик сценария деинсталляции приложения.
 *
 * Важно: Сценарий рассчитан на идеальные условия и может не учитывать случаи, когда:
 * 1) В базе данных кто-то вручную удалил или деактивировал запись о деинсталляции, а затем вызвал этот обработчик.
 * 2) Токен удаления поступает с задержкой (например, при быстрой установке и удалении приложения).
 * 3) В системе может существовать несколько аккаунтов, однако деинсталляция на портале выполняется только один раз.
 * 4) Для остальных аккаунтов деинсталляция невозможна — они используются только для авторизации и доступа.
 *
 * Возможные ограничения:
 * 1) Если установка приложения производилась по событию, которое не произошло, токен может не сохраниться, что приведет к спорным ситуациям при деинсталляции.
 * 2) Используйте этот обработчик только при уверенности в целостности данных и корректности порядка событий.
 */
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
        $this->logger->info('ApplicationInstallations.Uninstall.start', [
            'domainUrl' => $command->domainUrl,
            'memberId' => $command->memberId,
            'applicationToken' => $command->applicationToken,
        ]);

        /** @var AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
        $activeInstallation = $this->applicationInstallationRepository->findActiveByApplicationToken($command->applicationToken);

        if (null !== $activeInstallation) {
            $this->logger->info(
                'ApplicationInstallations.Uninstall.Start'
            );

            $entitiesToFlush = [];

            $activeInstallation->applicationUninstalled($command->applicationToken);

            $this->applicationInstallationRepository->save($activeInstallation);

            $entitiesToFlush[] = $activeInstallation;

            /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface[] $b24Accounts */
            $b24Accounts = $this->bitrix24AccountRepository->findByMemberId($command->memberId);

            if ([] !== $b24Accounts) {
                foreach ($b24Accounts as $b24Account) {
                    $b24Account->applicationUninstalled(null);
                    $this->bitrix24AccountRepository->save($b24Account);
                    $entitiesToFlush[] = $b24Account;
                }
            }

            $this->flusher->flush(...$entitiesToFlush);

            $this->logger->info('ApplicationInstallations.Uninstall.completed', [
                'installationId' => $activeInstallation->getId(),
                'applicationToken' => $command->applicationToken,
                'flushedEntitiesCount' => count($entitiesToFlush),
            ]);
        } else {
            $this->logger->info('ApplicationInstallations.Uninstall.false_request', [
                'applicationToken' => $command->applicationToken,
                'memberId' => $command->memberId,
                'domainUrl' => $command->domainUrl,
                'message' => 'No active installation found for uninstall request',
            ]);
        }

        $this->logger->info(
            'ApplicationInstallations.Uninstall.Finish'
        );
    }
}
