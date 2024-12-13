<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private Bitrix24AccountRepositoryInterface $bitrix24AccountRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    /**
     * @throws MultipleBitrix24AccountsFoundException
     */
    public function handle(Command $command): void
    {
        $this->logger->debug('Bitrix24Accounts.RenewAuthToken.start', [
            'domain_url' => $command->renewedAuthToken->domain,
            'member_id' => $command->renewedAuthToken->memberId,
            'bitrix24_user_id' => $command->bitrix24UserId,
        ]);

        $bitrix24Account = $this->getSingleAccountByMemberId(
            $command->renewedAuthToken->domain,
            $command->renewedAuthToken->memberId,
            Bitrix24AccountStatus::active,
            $command->bitrix24UserId
        );

        //  Bitrix24Account extends AggregateRoot and implement AggregateRootEventsEmitterInterface
        /** @var AggregateRootEventsEmitterInterface|Bitrix24AccountInterface $bitrix24Account */
        $bitrix24Account->renewAuthToken($command->renewedAuthToken);

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->flusher->flush($bitrix24Account);

        $this->logger->debug('Bitrix24Accounts.RenewAuthToken.finish');
    }

    /**
     * @throws MultipleBitrix24AccountsFoundException
     */
    public function getSingleAccountByMemberId(
        string $domainUrl,
        string $memberId,
        Bitrix24AccountStatus $bitrix24AccountStatus,
        ?int $bitrix24UserId
    ): Bitrix24AccountInterface {
        $accounts = $this->bitrix24AccountRepository->findByMemberId(
            $memberId,
            $bitrix24AccountStatus,
            $bitrix24UserId
        );

        if (null === $bitrix24UserId && count($accounts) > 1) {
            throw new MultipleBitrix24AccountsFoundException(
                sprintf(
                    'updating auth token failure - for domain %s with member id %s found multiple active accounts, try pass bitrix24_user_id in command',
                    $domainUrl,
                    $memberId
                )
            );
        }

        if (null !== $bitrix24UserId && count($accounts) > 1) {
            throw new MultipleBitrix24AccountsFoundException(
                sprintf(
                    'updating auth token failure - for domain %s with member id %s and bitrix24 user id %s found multiple active accounts',
                    $domainUrl,
                    $memberId,
                    $bitrix24UserId
                )
            );
        }

        return $accounts[0];
    }
}
