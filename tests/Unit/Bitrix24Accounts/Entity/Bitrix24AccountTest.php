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

namespace Bitrix24\SDK\Lib\Tests\Unit\Bitrix24Accounts\Entity;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\ApplicationCore\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Tests\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterfaceTest;
use Carbon\CarbonImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Bitrix24Account::class)]
class Bitrix24AccountTest extends Bitrix24AccountInterfaceTest
{
    #[Override]
    protected function createBitrix24AccountImplementation(
        Uuid                  $uuid,
        int                   $bitrix24UserId,
        bool                  $isBitrix24UserAdmin,
        string                $memberId,
        string                $domainUrl,
        Bitrix24AccountStatus $bitrix24AccountStatus,
        AuthToken             $authToken,
        CarbonImmutable       $createdAt,
        CarbonImmutable       $updatedAt,
        int                   $applicationVersion,
        Scope                 $applicationScope
    ): Bitrix24AccountInterface
    {
        return new Bitrix24Account(
            $uuid,
            $bitrix24UserId,
            $isBitrix24UserAdmin,
            $memberId,
            $domainUrl,
            $bitrix24AccountStatus,
            $authToken,
            $createdAt,
            $updatedAt,
            $applicationVersion,
            $applicationScope
        );
    }
}