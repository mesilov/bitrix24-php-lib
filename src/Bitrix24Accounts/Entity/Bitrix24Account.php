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

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\Entity;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\UnknownScopeCodeException;
use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;
use Carbon\CarbonImmutable;
use Override;
use Symfony\Component\Uid\Uuid;

class Bitrix24Account implements Bitrix24AccountInterface
{
    private string $accessToken;

    private string $refreshToken;

    private int $expires;

    private array $applicationScope;

    private ?string $applicationToken = null;

    private ?string $comment = null;

    public function __construct(
        private readonly Uuid            $id,
        private readonly int             $bitrix24UserId,
        private readonly bool            $isBitrix24UserAdmin,
        private readonly string          $memberId,
        private string                   $domainUrl,
        private Bitrix24AccountStatus    $accountStatus,
        AuthToken                        $authToken,
        private readonly CarbonImmutable $createdAt,
        private CarbonImmutable          $updatedAt,
        private int                      $applicationVersion,
        Scope                            $applicationScope,
    )
    {
        $this->accessToken = $authToken->getAccessToken();
        $this->refreshToken = $authToken->getRefreshToken();
        $this->expires = $authToken->getExpires();
        $this->applicationScope = $applicationScope->getScopeCodes();
    }

    #[Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[Override]
    public function getBitrix24UserId(): int
    {
        return $this->bitrix24UserId;
    }

    #[Override]
    public function isBitrix24UserAdmin(): bool
    {
        return $this->isBitrix24UserAdmin;
    }

    #[Override]
    public function getMemberId(): string
    {
        return $this->memberId;
    }

    #[Override]
    public function getDomainUrl(): string
    {
        return $this->domainUrl;
    }

    #[Override]
    public function getStatus(): Bitrix24AccountStatus
    {
        return $this->accountStatus;
    }

    #[Override]
    public function getAuthToken(): AuthToken
    {
        return new AuthToken($this->accessToken, $this->refreshToken, $this->expires);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
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

        $this->accessToken = $renewedAuthToken->authToken->getAccessToken();
        $this->refreshToken = $renewedAuthToken->authToken->getRefreshToken();
        $this->expires = $renewedAuthToken->authToken->getExpires();
        $this->updatedAt = new CarbonImmutable();
    }

    #[Override]
    public function getApplicationVersion(): int
    {
        return $this->applicationVersion;
    }

    /**
     * @throws UnknownScopeCodeException
     */
    #[Override]
    public function getApplicationScope(): Scope
    {
        return new Scope($this->applicationScope);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function changeDomainUrl(string $newDomainUrl): void
    {
        if ($newDomainUrl === '') {
            throw new InvalidArgumentException('new domain url cannot be empty');
        }

        if (Bitrix24AccountStatus::blocked === $this->accountStatus || Bitrix24AccountStatus::deleted === $this->accountStatus) {
            throw new InvalidArgumentException(
                sprintf(
                    'bitrix24 account %s for domain %s must be in active or new state, now account in %s state. domain url cannot be changed',
                    $this->id->toRfc4122(),
                    $this->domainUrl,
                    $this->accountStatus->name
                )
            );
        }

        $this->domainUrl = $newDomainUrl;
        $this->updatedAt = new CarbonImmutable();
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function applicationInstalled(string $applicationToken): void
    {
        if (Bitrix24AccountStatus::new !== $this->accountStatus) {
            throw new InvalidArgumentException(sprintf(
                'for finish installation bitrix24 account must be in status «new», current status - «%s»',
                $this->accountStatus->name));
        }

        if ($applicationToken === '') {
            throw new InvalidArgumentException('application token cannot be empty');
        }

        $this->accountStatus = Bitrix24AccountStatus::active;
        $this->applicationToken = $applicationToken;
        $this->updatedAt = new CarbonImmutable();
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function applicationUninstalled(string $applicationToken): void
    {
        if ($applicationToken === '') {
            throw new InvalidArgumentException('application token cannot be empty');
        }

        if (Bitrix24AccountStatus::active !== $this->accountStatus) {
            throw new InvalidArgumentException(sprintf(
                'for uninstall account must be in status «active», current status - «%s»',
                $this->accountStatus->name));
        }

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

        $this->accountStatus = Bitrix24AccountStatus::deleted;
        $this->updatedAt = new CarbonImmutable();
    }

    #[Override]
    public function isApplicationTokenValid(string $applicationToken): bool
    {
        return $this->applicationToken === $applicationToken;
    }

    #[Override]
    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    #[Override]
    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function updateApplicationVersion(int $version, ?Scope $newScope): void
    {
        if (Bitrix24AccountStatus::active !== $this->accountStatus) {
            throw new InvalidArgumentException(sprintf('account must be in status «active», but now account in status «%s»', $this->accountStatus->name));
        }

        if ($this->applicationVersion >= $version) {
            throw new InvalidArgumentException(
                sprintf('you cannot downgrade application version or set some version, current version «%s», but you try to upgrade to «%s»',
                    $this->applicationVersion,
                    $version));
        }

        $this->applicationVersion = $version;
        if ($newScope instanceof \Bitrix24\SDK\Core\Credentials\Scope) {
            $this->applicationScope = $newScope->getScopeCodes();
        }

        $this->updatedAt = new CarbonImmutable();
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function markAsActive(?string $comment): void
    {
        if (Bitrix24AccountStatus::blocked !== $this->accountStatus) {
            throw new InvalidArgumentException(
                sprintf('you can activate account only in status blocked, now account in status %s',
                    $this->accountStatus->name));
        }

        $this->accountStatus = Bitrix24AccountStatus::active;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    public function markAsBlocked(?string $comment): void
    {
        if (Bitrix24AccountStatus::deleted === $this->accountStatus) {
            throw new InvalidArgumentException('you cannot block account in status «deleted»');
        }

        $this->accountStatus = Bitrix24AccountStatus::blocked;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();
    }

    #[Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }
}