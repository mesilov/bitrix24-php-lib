<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationMarkedNeedReinstallEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private Handler $handler;

    private ApplicationInstallationRepository $installationRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->installationRepository = new ApplicationInstallationRepository($this->entityManager);

        $this->handler = new Handler(
            $this->installationRepository,
            new Flusher($this->entityManager, $this->eventDispatcher),
            new NullLogger()
        );
    }

    #[Test]
    public function testStaleInstallationIsMarkedAsNeedReinstall(): void
    {
        $bitrix24Account = $this->createAccount();
        $installation = $this->createInstallation($bitrix24Account->getId());

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($installation);
        $this->entityManager->flush();

        $this->backdateCreatedAt($installation->getId(), new CarbonImmutable('-2 hours'));

        $this->handler->handle(new Command(3600));
        $this->entityManager->clear();

        $updated = $this->installationRepository->getById($installation->getId());

        self::assertSame(ApplicationInstallationStatus::needReinstall, $updated->getStatus());

        $events = $this->eventDispatcher->getOrphanedEvents();
        self::assertContains(ApplicationInstallationMarkedNeedReinstallEvent::class, $events);
    }

    #[Test]
    public function testFreshInstallationStaysNew(): void
    {
        $bitrix24Account = $this->createAccount();
        $installation = $this->createInstallation($bitrix24Account->getId());

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($installation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->handler->handle(new Command(3600));

        $updated = $this->installationRepository->getById($installation->getId());

        self::assertSame(ApplicationInstallationStatus::new, $updated->getStatus());
    }

    #[Test]
    public function testNoStaleInstallationsDoesNothing(): void
    {
        $this->handler->handle(new Command(3600));

        $events = $this->eventDispatcher->getOrphanedEvents();
        self::assertNotContains(ApplicationInstallationMarkedNeedReinstallEvent::class, $events);
    }

    private function createAccount(): Bitrix24Account
    {
        return new Bitrix24Account(
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
    }

    private function createInstallation(Uuid $bitrix24AccountId): ApplicationInstallation
    {
        return new ApplicationInstallation(
            Uuid::v7(),
            $bitrix24AccountId,
            new ApplicationStatus('F'),
            PortalLicenseFamily::free,
            10,
            null,
            null,
            null,
            'lead-1',
            'install'
        );
    }

    private function backdateCreatedAt(Uuid $installationId, CarbonImmutable $createdAt): void
    {
        $this->entityManager->createQuery(
            'UPDATE ' . ApplicationInstallation::class . ' ai SET ai.createdAt = :createdAt WHERE ai.id = :id'
        )
            ->setParameter('createdAt', $createdAt)
            ->setParameter('id', $installationId, 'uuid')
            ->execute();
    }
}
