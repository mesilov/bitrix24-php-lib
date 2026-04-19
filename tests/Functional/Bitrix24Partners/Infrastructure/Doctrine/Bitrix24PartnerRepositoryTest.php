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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\FlusherDecorator;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Tests\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterfaceTest;
use Bitrix24\SDK\Tests\Application\Contracts\TestRepositoryFlusherInterface;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Bitrix24PartnerRepository::class)]
class Bitrix24PartnerRepositoryTest extends Bitrix24PartnerRepositoryInterfaceTest
{
    #[\Override]
    protected function createBitrix24PartnerImplementation(
        Uuid                  $uuid,
        CarbonImmutable       $createdAt,
        CarbonImmutable       $updatedAt,
        Bitrix24PartnerStatus $bitrix24PartnerStatus,
        string                $title,
        ?int                  $bitrix24PartnerNumber,
        ?string               $site,
        ?PhoneNumber          $phoneNumber,
        ?string               $email,
        ?string               $openLineId,
        ?string               $externalId
    ): Bitrix24PartnerInterface {
       return new Bitrix24Partner(
            $title,
            $bitrix24PartnerNumber,
            $site,
            $phoneNumber,
            $email,
            $openLineId,
            $externalId
        );
    }

    #[\Override]
    protected function createBitrix24PartnerRepositoryImplementation(): Bitrix24PartnerRepositoryInterface
    {
        $entityManager = EntityManagerFactory::get();

        return new Bitrix24PartnerRepository($entityManager);
    }

    #[\Override]
    protected function createRepositoryFlusherImplementation(): TestRepositoryFlusherInterface
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();

        return new FlusherDecorator(new Flusher($entityManager, $eventDispatcher));
    }
}
