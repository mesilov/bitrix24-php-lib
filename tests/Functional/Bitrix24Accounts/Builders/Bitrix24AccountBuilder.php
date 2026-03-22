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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

class Bitrix24AccountBuilder
{
    private readonly Uuid $id;

    private readonly int $bitrix24UserId;

    private readonly bool $isBitrix24UserAdmin;

    /** bitrix24 portal unique id */
    private string $memberId;

    private string $domainUrl;

    private Bitrix24AccountStatus $status;

    private readonly AuthToken $authToken;

    private readonly int $applicationVersion;

    private Scope $applicationScope;

    private ?string $applicationToken = null;

    private bool $isMasterAccount = false;

    private bool $isSetToken = false;

    private bool $isInstalled = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->bitrix24UserId = random_int(1, 1_000_000);
        $this->isBitrix24UserAdmin = true;
        $this->memberId = Uuid::v4()->toRfc4122();
        $this->domainUrl = Uuid::v4()->toRfc4122().'-example.com';
        $this->authToken = new AuthToken('old_1', 'old_2', 3600,time() + 60 * 60 * 24);
        $this->applicationVersion = 1;
        $this->applicationScope = new Scope();
    }

    public function withMemberId(string $memberId): self
    {
        $this->memberId = $memberId;

        return $this;
    }

    public function withDomainUrl(string $domainUrl): self
    {
        $this->domainUrl = $domainUrl;

        return $this;
    }

    public function withApplicationScope(Scope $applicationScope): self
    {
        $this->applicationScope = $applicationScope;

        return $this;
    }

    public function withApplicationToken(string $applicationToken): self
    {
        $this->applicationToken = $applicationToken;

        return $this;
    }

    public function withStatus(Bitrix24AccountStatus $bitrix24AccountStatus): self
    {
        $this->status = $bitrix24AccountStatus;

        return $this;
    }

    public function withSetToken(): self
    {
        $this->isSetToken = true;

        return $this;
    }

    public function withMaster(bool $isMaster): self
    {
        $this->isMasterAccount = $isMaster;

        return $this;
    }

    public function withInstalled(): self
    {
        $this->isInstalled = true;

        return $this;
    }

    public function build(): Bitrix24Account
    {
        $bitrix24Account = new Bitrix24Account(
            $this->id,
            $this->bitrix24UserId,
            $this->isBitrix24UserAdmin,
            $this->memberId,
            $this->domainUrl,
            $this->authToken,
            $this->applicationVersion,
            $this->applicationScope,
            $this->isMasterAccount
        );

        if ($this->isInstalled && Bitrix24AccountStatus::new == $this->status) {
            $bitrix24Account->applicationInstalled(null);
        }

        if ($this->isSetToken && $this->applicationToken !== null) {
            $bitrix24Account->setApplicationToken($this->applicationToken);
        }



        return $bitrix24Account;
    }
}
