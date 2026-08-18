<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Tests\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterfaceTest;
use Bitrix24\SDK\Tests\Application\Contracts\TestRepositoryFlusherInterface;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function testFindStaleInstallationsReturnsOnlyOldEnoughNewOnes(): void
    {
        $entityManager = EntityManagerFactory::get();
        $repository = new ApplicationInstallationRepository($entityManager);

        $oldInstallation = $this->persistInstallation($entityManager);
        $this->backdateCreatedAt($entityManager, $oldInstallation->getId(), new CarbonImmutable('-2 hours'));

        $freshInstallation = $this->persistInstallation($entityManager);
        $activeInstallation = $this->persistInstallation($entityManager);
        $activeInstallation->applicationInstalled(Uuid::v7()->toRfc4122());
        $this->backdateCreatedAt($entityManager, $activeInstallation->getId(), new CarbonImmutable('-2 hours'));
        $entityManager->flush();
        $entityManager->clear();

        $found = $repository->findStaleInstallations(
            ApplicationInstallationStatus::new,
            (new CarbonImmutable())->subSeconds(3600)
        );

        $foundIds = array_map(
            static fn (ApplicationInstallationInterface $installation): string => $installation->getId()->toRfc4122(),
            $found
        );

        self::assertContains($oldInstallation->getId()->toRfc4122(), $foundIds);
        self::assertNotContains($freshInstallation->getId()->toRfc4122(), $foundIds);
        self::assertNotContains($activeInstallation->getId()->toRfc4122(), $foundIds);
    }

    private function persistInstallation(EntityManagerInterface $entityManager): ApplicationInstallation
    {
        $bitrix24Account = new Bitrix24Account(
            Uuid::v7(),
            1,
            true,
            Uuid::v4()->toRfc4122(),
            'example.bitrix24.test',
            new AuthToken('access', 'refresh', 3600, time() + 3600),
            1,
            new Scope(['crm']),
            true
        );

        $applicationInstallation = new ApplicationInstallation(
            Uuid::v7(),
            $bitrix24Account->getId(),
            new ApplicationStatus('F'),
            PortalLicenseFamily::free,
            10,
            null,
            null,
            null,
            'lead-1',
            'install'
        );

        $entityManager->persist($bitrix24Account);
        $entityManager->persist($applicationInstallation);
        $entityManager->flush();

        return $applicationInstallation;
    }

    private function backdateCreatedAt(
        EntityManagerInterface $entityManager,
        Uuid $installationId,
        CarbonImmutable $createdAt
    ): void {
        $entityManager->createQuery(
            'UPDATE ' . ApplicationInstallation::class . ' ai SET ai.createdAt = :createdAt WHERE ai.id = :id'
        )
            ->setParameter('createdAt', $createdAt)
            ->setParameter('id', $installationId, 'uuid')
            ->execute();
    }

}

