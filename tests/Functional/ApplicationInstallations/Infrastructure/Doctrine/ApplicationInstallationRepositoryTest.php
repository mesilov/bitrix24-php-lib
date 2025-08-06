<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Tests\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterfaceTest;
use Bitrix24\SDK\Tests\Application\Contracts\TestRepositoryFlusherInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\Functional\FlusherDecorator;

/**
 * @internal
 */
#[CoversClass(ApplicationInstallationRepository::class)]
class ApplicationInstallationRepositoryTest extends ApplicationInstallationRepositoryInterfaceTest
{

    #[\Override]
    protected function createApplicationInstallationImplementation(
        Uuid                          $uuid,
        ApplicationInstallationStatus $applicationInstallationStatus,
        Uuid                          $bitrix24AccountUuid,
        ApplicationStatus             $applicationStatus,
        PortalLicenseFamily           $portalLicenseFamily,
        ?int                          $portalUsersCount,
        ?Uuid                         $clientContactPersonUuid,
        ?Uuid                         $partnerContactPersonUuid,
        ?Uuid                         $partnerUuid,
        ?string                       $externalId
    ): ApplicationInstallationInterface
    {
        return new ApplicationInstallation(
            $uuid,
            $bitrix24AccountUuid,
            $applicationStatus,
            $portalLicenseFamily,
            $portalUsersCount,
            $clientContactPersonUuid,
            $partnerContactPersonUuid,
            $partnerUuid,
            $externalId
        );
    }

    #[\Override]
    protected function createApplicationInstallationRepositoryImplementation(): ApplicationInstallationRepositoryInterface
    {
        $entityManager = EntityManagerFactory::get();

        return new ApplicationInstallationRepository($entityManager);
    }

    #[\Override]
    protected function createRepositoryFlusherImplementation(): TestRepositoryFlusherInterface
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();

        return new FlusherDecorator(new Flusher($entityManager, $eventDispatcher));
    }

}

