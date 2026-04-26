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

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\Entity;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Bitrix24\SDK\Tests\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterfaceTest;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Bitrix24Partner::class)]
class Bitrix24PartnerTest extends Bitrix24PartnerInterfaceTest
{
    #[\Override]
    protected function createBitrix24PartnerImplementation(
        Uuid                  $uuid,
        CarbonImmutable       $createdAt,
        CarbonImmutable       $updatedAt,
        Bitrix24PartnerStatus $bitrix24PartnerStatus,
        string                $title,
        int                  $bitrix24PartnerNumber,
        ?string               $site,
        ?PhoneNumber          $phoneNumber,
        ?string               $email,
        ?string               $openLineId,
        ?string               $externalId,
        ?string               $logoUrl = null
    ): Bitrix24PartnerInterface {
        return new Bitrix24Partner(
            $title,
            $bitrix24PartnerNumber,
            $site,
            $phoneNumber,
            $email,
            $openLineId,
            $externalId,
            $logoUrl
        );
    }
}
