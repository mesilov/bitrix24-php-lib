<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepository         $bitrix24AccountRepository,
        private Flusher                           $flusher,
        private LoggerInterface                   $logger
    )
    {
    }

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationInstallation.OnAppInstall.start', [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
        ]);
        /*
             Если при установке мы можем деактивировать аккаунты если их больше 1 по каким то причинам,
             то тут наверно лучше выдавать эксепшен , хотя с другой стороны это стоит обработать как то,
             но не хотелось бы этого делать здесь. Как лучше ????
        */
        
        /*
         * И еще вопрос токен будем в установку заносить все таки ? Потому что впринципи все разрулилось и без токена в установке.
         */
        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface $bitrix24Account */
        $bitrix24Account = $this->getSingleAccountByMemberId($command->domainUrl, $command->memberId,);

        $bitrix24Account->setToken($command->applicationToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);
        $this->logger->info('ApplicationInstallation.OnAppInstall.finish');
    }

    private function getSingleAccountByMemberId(string $domainUrl, string $memberId): Bitrix24AccountInterface
    {
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $memberId,
            Bitrix24AccountStatus::active,
        );

        if ([] === $accounts) {
            throw new Bitrix24AccountNotFoundException(
                sprintf(
                    'bitrix24 account for domain %s with member id %s in status «new» not found',
                    $domainUrl,
                    $memberId
                )
            );
        }

        if (count($accounts) > 1) {
            throw new MultipleBitrix24AccountsFoundException(
                sprintf(
                    'multiple bitrix24 accounts for domain %s with member id %s in status «new» found',
                    $domainUrl,
                    $memberId
                )
            );
        }

        return $accounts[0];
    }
}