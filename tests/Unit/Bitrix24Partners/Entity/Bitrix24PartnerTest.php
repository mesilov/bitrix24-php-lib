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
use Bitrix24\SDK\Tests\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterfaceTest;
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
        Uuid $uuid,
        string $title,
        ?string $site = null,
        ?PhoneNumber $phone = null,
        ?string $email = null,
        ?int $bitrix24PartnerId = null,
        ?string $openLineId = null,
        ?string $externalId = null
    ): Bitrix24PartnerInterface {
        // UUID parameter is ignored as it's generated internally
        // bitrix24PartnerId is required in our implementation, use default if null
        return new Bitrix24Partner(
            $title,
            $bitrix24PartnerId ?? 1,
            $site,
            $phone,
            $email,
            $openLineId,
            $externalId
        );
    }
}
