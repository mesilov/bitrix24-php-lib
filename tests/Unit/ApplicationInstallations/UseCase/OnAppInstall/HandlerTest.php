<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\Lib\Tests\Helpers\ApplicationInstallations\RecordingApplicationInstallationInMemoryRepository;
use Bitrix24\Lib\Tests\Helpers\Bitrix24Accounts\RecordingBitrix24AccountInMemoryRepository;
use Bitrix24\Lib\Tests\Helpers\CollectingLogger;
use Bitrix24\Lib\Tests\Helpers\SpyFlusher;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private RecordingBitrix24AccountInMemoryRepository $bitrix24AccountRepository;

    private RecordingApplicationInstallationInMemoryRepository $applicationInstallationRepository;

    private SpyFlusher $flusher;

    private CollectingLogger $logger;

    private Handler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->bitrix24AccountRepository = new RecordingBitrix24AccountInMemoryRepository(new NullLogger());
        $this->applicationInstallationRepository = new RecordingApplicationInstallationInMemoryRepository(
            $this->bitrix24AccountRepository
        );
        $this->flusher = new SpyFlusher();
        $this->logger = new CollectingLogger();

        $this->handler = new Handler(
            $this->bitrix24AccountRepository,
            $this->applicationInstallationRepository,
            $this->flusher,
            $this->logger
        );
    }

    #[Test]
    public function testHandleFinishesPendingInstallation(): void
    {
        $command = $this->createCommand();
        $bitrix24Account = $this->createAccount($command->memberId, true);
        $applicationInstallation = $this->createInstallation($bitrix24Account->getId());

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);

        $bitrix24AccountSaveCalls = $this->bitrix24AccountRepository->getSaveCalls();
        $applicationInstallationSaveCalls = $this->applicationInstallationRepository->getSaveCalls();

        $this->handler->handle($command);

        $storedInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);
        self::assertNotNull($storedInstallation);
        self::assertSame(ApplicationInstallationStatus::active, $storedInstallation->getStatus());
        self::assertSame(Bitrix24AccountStatus::active, $bitrix24Account->getStatus());
        self::assertTrue($storedInstallation->isApplicationTokenValid($command->applicationToken));
        self::assertTrue($bitrix24Account->isApplicationTokenValid($command->applicationToken));
        self::assertSame($bitrix24AccountSaveCalls + 1, $this->bitrix24AccountRepository->getSaveCalls());
        self::assertSame($applicationInstallationSaveCalls + 1, $this->applicationInstallationRepository->getSaveCalls());
        self::assertCount(1, $this->flusher->getFlushCalls());
        self::assertSame([$storedInstallation, $bitrix24Account], $this->flusher->getFlushCalls()[0]);
    }

    #[Test]
    public function testHandleDuplicateEventWithSameTokenIsNoop(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand($applicationToken, new ApplicationStatus('T'));
        $bitrix24Account = $this->createActiveAccount($command->memberId, $applicationToken, true);
        $applicationInstallation = $this->createActiveInstallation(
            $bitrix24Account->getId(),
            $applicationToken,
            new ApplicationStatus('F')
        );

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);

        $bitrix24AccountSaveCalls = $this->bitrix24AccountRepository->getSaveCalls();
        $applicationInstallationSaveCalls = $this->applicationInstallationRepository->getSaveCalls();

        $this->handler->handle($command);

        $storedInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);
        self::assertNotNull($storedInstallation);
        self::assertSame(ApplicationInstallationStatus::active, $storedInstallation->getStatus());
        self::assertSame('free', $storedInstallation->getApplicationStatus()->getStatusCode());
        self::assertTrue($storedInstallation->isApplicationTokenValid($applicationToken));
        self::assertTrue($bitrix24Account->isApplicationTokenValid($applicationToken));
        self::assertSame($bitrix24AccountSaveCalls, $this->bitrix24AccountRepository->getSaveCalls());
        self::assertSame($applicationInstallationSaveCalls, $this->applicationInstallationRepository->getSaveCalls());
        self::assertCount(0, $this->flusher->getFlushCalls());
        self::assertSame(1, $this->logger->countByLevel(LogLevel::WARNING));
        self::assertTrue($this->logger->recordsByLevel(LogLevel::WARNING)[0]['context']['tokenMatch']);
    }

    #[Test]
    public function testHandleRepeatedEventWithDifferentTokenIsNoop(): void
    {
        $storedToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand(Uuid::v7()->toRfc4122(), new ApplicationStatus('T'));
        $bitrix24Account = $this->createActiveAccount($command->memberId, $storedToken, true);
        $applicationInstallation = $this->createActiveInstallation(
            $bitrix24Account->getId(),
            $storedToken,
            new ApplicationStatus('F')
        );

        $this->bitrix24AccountRepository->save($bitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);

        $bitrix24AccountSaveCalls = $this->bitrix24AccountRepository->getSaveCalls();
        $applicationInstallationSaveCalls = $this->applicationInstallationRepository->getSaveCalls();

        $this->handler->handle($command);

        $storedInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);
        self::assertNotNull($storedInstallation);
        self::assertSame(ApplicationInstallationStatus::active, $storedInstallation->getStatus());
        self::assertSame('free', $storedInstallation->getApplicationStatus()->getStatusCode());
        self::assertTrue($storedInstallation->isApplicationTokenValid($storedToken));
        self::assertTrue($bitrix24Account->isApplicationTokenValid($storedToken));
        self::assertSame($bitrix24AccountSaveCalls, $this->bitrix24AccountRepository->getSaveCalls());
        self::assertSame($applicationInstallationSaveCalls, $this->applicationInstallationRepository->getSaveCalls());
        self::assertCount(0, $this->flusher->getFlushCalls());
        self::assertSame(1, $this->logger->countByLevel(LogLevel::WARNING));
        self::assertFalse($this->logger->recordsByLevel(LogLevel::WARNING)[0]['context']['tokenMatch']);
    }

    #[Test]
    public function testHandleThrowsWhenPendingInstallationNotFound(): void
    {
        $command = $this->createCommand();

        $this->expectException(ApplicationInstallationNotFoundException::class);

        try {
            $this->handler->handle($command);
        } finally {
            self::assertCount(0, $this->flusher->getFlushCalls());
            self::assertSame(0, $this->bitrix24AccountRepository->getSaveCalls());
            self::assertSame(0, $this->applicationInstallationRepository->getSaveCalls());
        }
    }

    #[Test]
    public function testHandleThrowsWhenPendingInstallationExistsButNewMasterAccountMissing(): void
    {
        $command = $this->createCommand();
        $activeBitrix24Account = $this->createActiveAccount($command->memberId, Uuid::v7()->toRfc4122(), true);
        $applicationInstallation = $this->createInstallation($activeBitrix24Account->getId());

        $this->bitrix24AccountRepository->save($activeBitrix24Account);
        $this->applicationInstallationRepository->save($applicationInstallation);

        $bitrix24AccountSaveCalls = $this->bitrix24AccountRepository->getSaveCalls();
        $applicationInstallationSaveCalls = $this->applicationInstallationRepository->getSaveCalls();

        $this->expectException(Bitrix24AccountNotFoundException::class);

        try {
            $this->handler->handle($command);
        } finally {
            self::assertCount(0, $this->flusher->getFlushCalls());
            self::assertSame($bitrix24AccountSaveCalls, $this->bitrix24AccountRepository->getSaveCalls());
            self::assertSame($applicationInstallationSaveCalls, $this->applicationInstallationRepository->getSaveCalls());
        }
    }

    private function createCommand(?string $applicationToken = null, ?ApplicationStatus $applicationStatus = null): Command
    {
        return new Command(
            memberId: Uuid::v4()->toRfc4122(),
            domainUrl: new Domain('example.bitrix24.test'),
            applicationToken: $applicationToken ?? Uuid::v7()->toRfc4122(),
            applicationStatus: $applicationStatus ?? new ApplicationStatus('T')
        );
    }

    private function createAccount(string $memberId, bool $isMaster): Bitrix24Account
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
            $isMaster
        );
    }

    private function createActiveAccount(string $memberId, string $applicationToken, bool $isMaster): Bitrix24Account
    {
        $bitrix24Account = $this->createAccount($memberId, $isMaster);
        $bitrix24Account->applicationInstalled($applicationToken);

        return $bitrix24Account;
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

    private function createActiveInstallation(
        Uuid $uuid,
        string $applicationToken,
        ApplicationStatus $applicationStatus
    ): ApplicationInstallation {
        $applicationInstallation = new ApplicationInstallation(
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
        $applicationInstallation->applicationInstalled($applicationToken);

        return $applicationInstallation;
    }
}
