<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationBlockedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationUninstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationUninstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
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

    private Bitrix24AccountRepository $accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->installationRepository = new ApplicationInstallationRepository($this->entityManager);
        $this->accountRepository = new Bitrix24AccountRepository($this->entityManager);

        $this->handler = new Handler(
            $this->accountRepository,
            $this->installationRepository,
            new Flusher($this->entityManager, $this->eventDispatcher),
            new NullLogger()
        );
    }

    #[Test]
    public function testInstallWithoutTokenCreatesPendingEntities(): void
    {
        $command = $this->createCommand();

        $this->handler->handle($command);
        $this->entityManager->clear();

        $installation = $this->installationRepository->findByBitrix24AccountMemberId($command->memberId);
        $accounts = $this->accountRepository->findByMemberId($command->memberId);
        $account = $accounts[0];

        self::assertNotNull($installation);
        self::assertSame(ApplicationInstallationStatus::new, $installation->getStatus());
        self::assertSame(Bitrix24AccountStatus::new, $account->getStatus());

        $events = $this->eventDispatcher->getOrphanedEvents();
        self::assertContains(Bitrix24AccountCreatedEvent::class, $events);
        self::assertContains(ApplicationInstallationCreatedEvent::class, $events);
        self::assertNotContains(Bitrix24AccountApplicationInstalledEvent::class, $events);
        self::assertNotContains(ApplicationInstallationFinishedEvent::class, $events);
    }

    #[Test]
    public function testInstallWithTokenFinishesInstallationInOneStep(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand($applicationToken);

        $this->handler->handle($command);
        $this->entityManager->clear();

        $installation = $this->installationRepository->findByApplicationToken($applicationToken);
        $accounts = $this->accountRepository->findByApplicationToken($applicationToken);

        self::assertNotNull($installation);
        self::assertCount(1, $accounts);
        self::assertSame(ApplicationInstallationStatus::active, $installation->getStatus());
        self::assertSame(Bitrix24AccountStatus::active, $accounts[0]->getStatus());

        $events = $this->eventDispatcher->getOrphanedEvents();
        self::assertContains(Bitrix24AccountCreatedEvent::class, $events);
        self::assertContains(ApplicationInstallationCreatedEvent::class, $events);
        self::assertContains(Bitrix24AccountApplicationInstalledEvent::class, $events);
        self::assertContains(ApplicationInstallationFinishedEvent::class, $events);
    }

    #[Test]
    public function testReinstallOverPendingInstallationDeletesOldEntitiesAndCreatesNewPendingPair(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        $bitrix24Account = $this->createAccount($memberId);
        $applicationInstallation = $this->createInstallation($bitrix24Account->getId());

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($applicationInstallation);
        $this->entityManager->flush();

        $this->handler->handle($this->createCommand(null, $memberId));
        $this->entityManager->clear();

        /** @var Bitrix24Account $deletedAccount */
        $deletedAccount = $this->entityManager->find(Bitrix24Account::class, $bitrix24Account->getId());
        /** @var ApplicationInstallation $deletedInstallation */
        $deletedInstallation = $this->entityManager->find(ApplicationInstallation::class, $applicationInstallation->getId());
        $currentInstallation = $this->installationRepository->findByBitrix24AccountMemberId($memberId);

        self::assertNotNull($currentInstallation);
        self::assertSame(Bitrix24AccountStatus::deleted, $deletedAccount->getStatus());
        self::assertSame(ApplicationInstallationStatus::deleted, $deletedInstallation->getStatus());
        self::assertSame('reinstall before finish', $deletedInstallation->getComment());
        self::assertSame(ApplicationInstallationStatus::new, $currentInstallation->getStatus());
        self::assertNotSame($applicationInstallation->getId()->toRfc4122(), $currentInstallation->getId()->toRfc4122());

        $events = $this->eventDispatcher->getOrphanedEvents();
        self::assertContains(ApplicationInstallationBlockedEvent::class, $events);
        self::assertContains(ApplicationInstallationUninstalledEvent::class, $events);
        self::assertContains(Bitrix24AccountApplicationUninstalledEvent::class, $events);
        self::assertContains(Bitrix24AccountCreatedEvent::class, $events);
        self::assertContains(ApplicationInstallationCreatedEvent::class, $events);
        self::assertNotContains(Bitrix24AccountApplicationInstalledEvent::class, $events);
        self::assertNotContains(ApplicationInstallationFinishedEvent::class, $events);
    }

    private function createCommand(?string $applicationToken = null, ?string $memberId = null): Command
    {
        return new Command(
            memberId: $memberId ?? Uuid::v4()->toRfc4122(),
            domain: new Domain('example.bitrix24.test'),
            authToken: new AuthToken('access', 'refresh', 3600, time() + 3600),
            applicationVersion: 1,
            applicationScope: new Scope(['crm']),
            bitrix24UserId: 1,
            isBitrix24UserAdmin: true,
            applicationStatus: new ApplicationStatus('F'),
            portalLicenseFamily: PortalLicenseFamily::free,
            applicationToken: $applicationToken,
            portalUsersCount: 10,
            externalId: 'lead-1',
            comment: 'install'
        );
    }

    private function createAccount(string $memberId): Bitrix24Account
    {
        return new Bitrix24Account(
            Uuid::v7(),
            1,
            true,
            $memberId,
            'example.bitrix24.test',
            new AuthToken('access', 'refresh', 3600, time() + 3600),
            1,
            new Scope(['crm']),
            true
        );
    }

    private function createInstallation(Uuid $uuid): ApplicationInstallation
    {
        return new ApplicationInstallation(
            Uuid::v7(),
            $uuid,
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
}
