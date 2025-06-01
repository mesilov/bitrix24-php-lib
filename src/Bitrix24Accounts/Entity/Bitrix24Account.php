<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationUninstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationVersionUpdatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountBlockedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountDomainUrlChangedEvent;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

class Bitrix24Account extends AggregateRoot implements Bitrix24AccountInterface
{
    private ?string $applicationToken = null;

    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    private Bitrix24AccountStatus $status;

    public function __construct(
        private readonly Uuid   $id,
        private readonly int    $bitrix24UserId,
        private readonly bool   $isBitrix24UserAdmin,
        /** bitrix24 portal unique id */
        private readonly string $memberId,
        private string          $domainUrl,
        private AuthToken       $authToken,
        private int             $applicationVersion,
        private Scope           $applicationScope,
        private readonly bool   $isMasterAccount = false,
        private                 $isEmitBitrix24AccountCreatedEvent = false,
        private ?string         $comment = null
    ) {
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
        $this->status = Bitrix24AccountStatus::new;
        $this->addAccountCreatedEventIfNeeded($this->isEmitBitrix24AccountCreatedEvent);
    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[\Override]
    public function getBitrix24UserId(): int
    {
        return $this->bitrix24UserId;
    }

    #[\Override]
    public function isBitrix24UserAdmin(): bool
    {
        return $this->isBitrix24UserAdmin;
    }

    #[\Override]
    public function getMemberId(): string
    {
        return $this->memberId;
    }

    #[\Override]
    public function getDomainUrl(): string
    {
        return $this->domainUrl;
    }

    #[\Override]
    public function getStatus(): Bitrix24AccountStatus
    {
        return $this->status;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getAuthToken(): AuthToken
    {
        return $this->authToken;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function renewAuthToken(RenewedAuthToken $renewedAuthToken): void
    {
        if ($this->memberId !== $renewedAuthToken->memberId) {
            throw new InvalidArgumentException(
                sprintf(
                    'member id %s for bitrix24 account %s for domain %s mismatch with member id %s for renewed access token',
                    $this->memberId,
                    $this->id->toRfc4122(),
                    $this->domainUrl,
                    $renewedAuthToken->memberId,
                )
            );
        }

        $this->authToken = new AuthToken(
            $renewedAuthToken->authToken->accessToken,
            $renewedAuthToken->authToken->refreshToken,
            $renewedAuthToken->authToken->expires
        );

        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function getApplicationVersion(): int
    {
        return $this->applicationVersion;
    }

    #[\Override]
    public function getApplicationScope(): Scope
    {
        return $this->applicationScope;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function changeDomainUrl(string $newDomainUrl): void
    {
        if ('' === $newDomainUrl) {
            throw new InvalidArgumentException('new domain url cannot be empty');
        }

        if (Bitrix24AccountStatus::blocked === $this->status || Bitrix24AccountStatus::deleted === $this->status) {
            throw new InvalidArgumentException(
                sprintf(
                    'bitrix24 account %s for domain %s must be in active or new state, now account in %s state. domain url cannot be changed',
                    $this->id->toRfc4122(),
                    $this->domainUrl,
                    $this->status->name
                )
            );
        }

        $this->domainUrl = $newDomainUrl;
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new Bitrix24AccountDomainUrlChangedEvent(
            $this->id,
            new CarbonImmutable()
        );
    }

    /**
     * @param string|null $applicationToken
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function applicationInstalled(?string $applicationToken): void
    {
        if (Bitrix24AccountStatus::new !== $this->status) {
            throw new InvalidArgumentException(
                sprintf(
                    'for finish installation bitrix24 account must be in status «new», current status - «%s»',
                    $this->status->name
                )
            );
        }

        if ('' !== $applicationToken) {
            $this->applicationToken = $applicationToken;
        }

        $this->status = Bitrix24AccountStatus::active;
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new Bitrix24AccountApplicationInstalledEvent(
            $this->id,
            new CarbonImmutable()
        );
    }


    #[\Override]
    public function applicationUninstalled(?string $applicationToken): void
    {
        $this->status = Bitrix24AccountStatus::deleted;
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new Bitrix24AccountApplicationUninstalledEvent(
            $this->id,
            new CarbonImmutable()
        );
    }

    #[\Override]
    public function isApplicationTokenValid(string $applicationToken): bool
    {
        $this->guardTokenMismatch($applicationToken);

        return $this->applicationToken === $applicationToken;
    }

    #[\Override]
    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    #[\Override]
    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function updateApplicationVersion(AuthToken $authToken, int $b24UserId, int $version, ?Scope $newScope): void
    {
        if (Bitrix24AccountStatus::active !== $this->status) {
            throw new InvalidArgumentException(
                sprintf('account must be in status «active», but now account in status «%s»', $this->status->name)
            );
        }

        if ($this->applicationVersion >= $version) {
            throw new InvalidArgumentException(
                sprintf(
                    'you cannot downgrade application version or set some version, current version «%s», but you try to upgrade to «%s»',
                    $this->applicationVersion,
                    $version
                )
            );
        }

        $this->applicationVersion = $version;
        if ($newScope instanceof Scope) {
            $this->applicationScope = $newScope;
        }

        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new Bitrix24AccountApplicationVersionUpdatedEvent(
            $this->id,
            new CarbonImmutable()
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function markAsActive(?string $comment): void
    {
        if (Bitrix24AccountStatus::blocked !== $this->status) {
            throw new InvalidArgumentException(
                sprintf(
                    'you can activate account only in status «blocked», now account in status «%s»',
                    $this->status->name
                )
            );
        }

        $this->status = Bitrix24AccountStatus::active;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();
    }


    #[\Override]
    public function isMasterAccount(): bool
    {
        return $this->isMasterAccount;
    }

    #[\Override]
    public function setApplicationToken(string $applicationToken): void
    {
        $this->guardEmptyToken($applicationToken);

        $this->updatedAt = new CarbonImmutable();
        $this->applicationToken = $applicationToken;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        if (Bitrix24AccountStatus::deleted === $this->status) {
            throw new InvalidArgumentException('you cannot block account in status «deleted»');
        }

        $this->status = Bitrix24AccountStatus::blocked;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new Bitrix24AccountBlockedEvent(
            $this->id,
            new CarbonImmutable(),
            $this->comment
        );
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    private function guardEmptyToken(string $applicationToken): void
    {
        if ('' === $applicationToken) {
            throw new InvalidArgumentException('application token cannot be empty');
        }
    }

    private function guardTokenMismatch(string $applicationToken): void
    {
        if ($this->applicationToken !== $applicationToken) {
            throw new InvalidArgumentException(
                sprintf(
                    'application token «%s» mismatch with application token «%s» for bitrix24 account %s for domain %s',
                    $applicationToken,
                    $this->applicationToken,
                    $this->id->toRfc4122(),
                    $this->domainUrl
                )
            );
        }
    }

    private function guardApplicationIsActive(): void
    {
        if (Bitrix24AccountStatus::active !== $this->status) {
            throw new InvalidArgumentException(
                sprintf(
                    'for uninstall account must be in status «active», current status - «%s»',
                    $this->status->name
                )
            );
        }
    }

    private function addAccountCreatedEventIfNeeded(bool $isEmitCreatedEvent): void
    {
        if ($isEmitCreatedEvent) {
            // Создание события и добавление его в массив событий
            $this->events[] = new Bitrix24AccountCreatedEvent(
                $this->id,
                $this->createdAt
            );
        }
    }

}
