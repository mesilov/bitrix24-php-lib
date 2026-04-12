<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
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

    private ApplicationInstallationRepository $installationRepository;

    private Bitrix24AccountRepository $accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    private TestLogger $logger;

    private Handler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->installationRepository = new ApplicationInstallationRepository($this->entityManager);
        $this->accountRepository = new Bitrix24AccountRepository($this->entityManager);
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->logger = new TestLogger();

        $this->handler = new Handler(
            $this->accountRepository,
            $this->installationRepository,
            new Flusher($this->entityManager, $this->eventDispatcher),
            $this->logger
        );
    }

    #[Test]
    public function testOnAppInstallFinishesPendingInstallation(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account = $this->createAccount($memberId);
        $applicationInstallation = $this->createInstallation($bitrix24Account->getId(), new ApplicationStatus('F'));

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($applicationInstallation);
        $this->entityManager->flush();

        $this->handler->handle(new Command(
            memberId: $memberId,
            domainUrl: new Domain('example.bitrix24.test'),
            applicationToken: $applicationToken,
            applicationStatus: new ApplicationStatus('T')
        ));

        $this->entityManager->clear();

        $updatedInstallation = $this->installationRepository->findByBitrix24AccountMemberId($memberId);
        $updatedAccount = $this->accountRepository->findByMemberId($memberId, Bitrix24AccountStatus::active, null, null, true)[0];

        self::assertNotNull($updatedInstallation);
        self::assertSame(ApplicationInstallationStatus::active, $updatedInstallation->getStatus());
        self::assertSame(Bitrix24AccountStatus::active, $updatedAccount->getStatus());
        self::assertSame('trial', $updatedInstallation->getApplicationStatus()->getStatusCode());
        self::assertTrue($updatedInstallation->isApplicationTokenValid($applicationToken));
        self::assertTrue($updatedAccount->isApplicationTokenValid($applicationToken));
        self::assertContains(ApplicationInstallationFinishedEvent::class, $this->eventDispatcher->getOrphanedEvents());
        self::assertContains(Bitrix24AccountApplicationInstalledEvent::class, $this->eventDispatcher->getOrphanedEvents());
    }

    #[Test]
    public function testDuplicateOnAppInstallWithSameTokenIsNoop(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account = $this->createActiveAccount($memberId, $applicationToken);
        $applicationInstallation = $this->createActiveInstallation($bitrix24Account->getId(), $applicationToken, new ApplicationStatus('F'));

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($applicationInstallation);
        $this->entityManager->flush();

        $this->handler->handle(new Command(
            memberId: $memberId,
            domainUrl: new Domain('example.bitrix24.test'),
            applicationToken: $applicationToken,
            applicationStatus: new ApplicationStatus('T')
        ));

        $this->entityManager->clear();

        $updatedInstallation = $this->installationRepository->findByBitrix24AccountMemberId($memberId);
        $updatedAccount = $this->accountRepository->findByMemberId($memberId, Bitrix24AccountStatus::active, null, null, true)[0];

        self::assertSame(ApplicationInstallationStatus::active, $updatedInstallation->getStatus());
        self::assertSame('free', $updatedInstallation->getApplicationStatus()->getStatusCode());
        self::assertTrue($updatedInstallation->isApplicationTokenValid($applicationToken));
        self::assertTrue($updatedAccount->isApplicationTokenValid($applicationToken));
        self::assertSame([], $this->eventDispatcher->getOrphanedEvents());
        self::assertCount(1, $this->logger->warnings);
        self::assertTrue($this->logger->warnings[0]['context']['tokenMatch']);
    }

    #[Test]
    public function testRepeatedOnAppInstallWithDifferentTokenIsNoop(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        $storedToken = Uuid::v7()->toRfc4122();
        $replayedToken = Uuid::v7()->toRfc4122();
        $bitrix24Account = $this->createActiveAccount($memberId, $storedToken);
        $applicationInstallation = $this->createActiveInstallation($bitrix24Account->getId(), $storedToken, new ApplicationStatus('F'));

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($applicationInstallation);
        $this->entityManager->flush();

        $this->handler->handle(new Command(
            memberId: $memberId,
            domainUrl: new Domain('example.bitrix24.test'),
            applicationToken: $replayedToken,
            applicationStatus: new ApplicationStatus('T')
        ));

        $this->entityManager->clear();

        $updatedInstallation = $this->installationRepository->findByBitrix24AccountMemberId($memberId);
        $updatedAccount = $this->accountRepository->findByMemberId($memberId, Bitrix24AccountStatus::active, null, null, true)[0];

        self::assertSame('free', $updatedInstallation->getApplicationStatus()->getStatusCode());
        self::assertTrue($updatedInstallation->isApplicationTokenValid($storedToken));
        self::assertTrue($updatedAccount->isApplicationTokenValid($storedToken));
        self::assertSame([], $this->eventDispatcher->getOrphanedEvents());
        self::assertCount(1, $this->logger->warnings);
        self::assertFalse($this->logger->warnings[0]['context']['tokenMatch']);
    }

    #[Test]
    public function testOnAppInstallThrowsWhenPendingInstallationMissing(): void
    {
        $this->expectException(ApplicationInstallationNotFoundException::class);

        $this->handler->handle(new Command(
            memberId: Uuid::v4()->toRfc4122(),
            domainUrl: new Domain('example.bitrix24.test'),
            applicationToken: Uuid::v7()->toRfc4122(),
            applicationStatus: new ApplicationStatus('T')
        ));
    }

    #[Test]
    public function testOnAppInstallThrowsWhenPendingInstallationExistsButNewMasterAccountMissing(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        $bitrix24Account = $this->createActiveAccount($memberId, Uuid::v7()->toRfc4122());
        $applicationInstallation = $this->createInstallation($bitrix24Account->getId(), new ApplicationStatus('F'));

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($applicationInstallation);
        $this->entityManager->flush();

        $this->expectException(Bitrix24AccountNotFoundException::class);

        $this->handler->handle(new Command(
            memberId: $memberId,
            domainUrl: new Domain('example.bitrix24.test'),
            applicationToken: Uuid::v7()->toRfc4122(),
            applicationStatus: new ApplicationStatus('T')
        ));
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

    private function createActiveAccount(string $memberId, string $applicationToken): Bitrix24Account
    {
        $bitrix24Account = $this->createAccount($memberId);
        $bitrix24Account->applicationInstalled($applicationToken);

        return $bitrix24Account;
    }

    private function createInstallation(Uuid $uuid, ApplicationStatus $applicationStatus): ApplicationInstallation
    {
        return new ApplicationInstallation(
            Uuid::v7(),
            $uuid,
            $applicationStatus,
            PortalLicenseFamily::free,
            10,
            null,
            null,
            null,
            'lead-1',
            'install'
        );
    }

    private function createActiveInstallation(
        Uuid $uuid,
        string $applicationToken,
        ApplicationStatus $applicationStatus
    ): ApplicationInstallation {
        $applicationInstallation = $this->createInstallation($uuid, $applicationStatus);
        $applicationInstallation->applicationInstalled($applicationToken);

        return $applicationInstallation;
    }
}

final class TestLogger extends AbstractLogger
{
    /** @var array<int, array{message: string, context: array<mixed>}> */
    public array $warnings = [];

    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ('warning' === $level) {
            $this->warnings[] = [
                'message' => (string) $message,
                'context' => $context,
            ];
        }
    }
}
