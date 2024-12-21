<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws Bitrix24AccountNotFoundException
     * @throws MultipleBitrix24AccountsFoundException
     * @throws InvalidArgumentException
     */
    public function handle(Command $command): void
    {
        $this->logger->info('Bitrix24Accounts.InstallFinish.start', [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
            'b24_user_id' => $command->bitrix24UserId,
        ]);

        /** @var Bitrix24AccountInterface|AggregateRootEventsEmitterInterface $bitrix24Account */
        $bitrix24Account = $this->getSingleAccountByMemberId($command->domainUrl, $command->memberId, $command->bitrix24UserId);

        $bitrix24Account->applicationInstalled($command->applicationToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);


        $this->logger->info('Bitrix24Accounts.InstallFinish.Finish',
        [
            'b24_domain_url' => $command->domainUrl,
            'b24_member_id' => $command->memberId,
            'b24_application_id' => $command->applicationToken,
            'b24_user_id' => $command->bitrix24UserId,
        ]);
    }

    private function getSingleAccountByMemberId(string $domainUrl, string $memberId, ?int $bitrix24UserId): Bitrix24AccountInterface
    {
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $memberId,
            Bitrix24AccountStatus::new,
            $bitrix24UserId
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
