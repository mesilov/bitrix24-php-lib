<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\OnAppInstall;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\OnAppInstall\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24AccountRepositoryInterface&MockObject $bitrix24AccountRepository;

    private ApplicationInstallationRepositoryInterface&MockObject $applicationInstallationRepository;

    private Flusher&MockObject $flusher;

    private LoggerInterface&MockObject $logger;

    private Handler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->bitrix24AccountRepository = $this->createMock(Bitrix24AccountRepositoryInterface::class);
        $this->applicationInstallationRepository = $this->createMock(ApplicationInstallationRepositoryInterface::class);
        $this->flusher = $this->createMock(Flusher::class);
        $this->logger = $this->createMock(LoggerInterface::class);

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

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn($applicationInstallation);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('findByMemberId')
            ->with($command->memberId, Bitrix24AccountStatus::new, null, null)
            ->willReturn([$bitrix24Account]);

        $this->applicationInstallationRepository->expects($this->once())->method('save')->with($applicationInstallation);
        $this->bitrix24AccountRepository->expects($this->once())->method('save')->with($bitrix24Account);

        $this->flusher
            ->expects($this->once())
            ->method('flush')
            ->with($applicationInstallation, $bitrix24Account);

        $this->handler->handle($command);

        self::assertSame(ApplicationInstallationStatus::active, $applicationInstallation->getStatus());
        self::assertSame(Bitrix24AccountStatus::active, $bitrix24Account->getStatus());
        self::assertTrue($applicationInstallation->isApplicationTokenValid($command->applicationToken));
        self::assertTrue($bitrix24Account->isApplicationTokenValid($command->applicationToken));
    }

    #[Test]
    public function testHandleDuplicateEventWithSameTokenIsNoop(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand($applicationToken, new ApplicationStatus('T'));
        $bitrix24Account = $this->createActiveAccount($command->memberId, $applicationToken, true);
        $applicationInstallation = $this->createActiveInstallation($bitrix24Account->getId(), $applicationToken, new ApplicationStatus('F'));

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn($applicationInstallation);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('findByMemberId')
            ->with($command->memberId, Bitrix24AccountStatus::active, null, null)
            ->willReturn([$bitrix24Account]);

        $this->applicationInstallationRepository->expects($this->never())->method('save');
        $this->bitrix24AccountRepository->expects($this->never())->method('save');
        $this->flusher->expects($this->never())->method('flush');
        $this->logger->expects($this->once())->method('warning');

        $this->handler->handle($command);

        self::assertSame(ApplicationInstallationStatus::active, $applicationInstallation->getStatus());
        self::assertSame('free', $applicationInstallation->getApplicationStatus()->getStatusCode());
        self::assertTrue($applicationInstallation->isApplicationTokenValid($applicationToken));
        self::assertTrue($bitrix24Account->isApplicationTokenValid($applicationToken));
    }

    #[Test]
    public function testHandleRepeatedEventWithDifferentTokenIsNoop(): void
    {
        $storedToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand(Uuid::v7()->toRfc4122(), new ApplicationStatus('T'));
        $bitrix24Account = $this->createActiveAccount($command->memberId, $storedToken, true);
        $applicationInstallation = $this->createActiveInstallation($bitrix24Account->getId(), $storedToken, new ApplicationStatus('F'));

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn($applicationInstallation);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('findByMemberId')
            ->with($command->memberId, Bitrix24AccountStatus::active, null, null)
            ->willReturn([$bitrix24Account]);

        $this->applicationInstallationRepository->expects($this->never())->method('save');
        $this->bitrix24AccountRepository->expects($this->never())->method('save');
        $this->flusher->expects($this->never())->method('flush');
        $this->logger->expects($this->once())->method('warning');

        $this->handler->handle($command);

        self::assertTrue($applicationInstallation->isApplicationTokenValid($storedToken));
        self::assertTrue($bitrix24Account->isApplicationTokenValid($storedToken));
        self::assertSame('free', $applicationInstallation->getApplicationStatus()->getStatusCode());
    }

    #[Test]
    public function testHandleThrowsWhenPendingInstallationNotFound(): void
    {
        $command = $this->createCommand();

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn(null);

        $this->bitrix24AccountRepository->expects($this->never())->method('findByMemberId');
        $this->flusher->expects($this->never())->method('flush');

        $this->expectException(ApplicationInstallationNotFoundException::class);

        $this->handler->handle($command);
    }

    #[Test]
    public function testHandleThrowsWhenPendingInstallationExistsButNewMasterAccountMissing(): void
    {
        $command = $this->createCommand();
        $applicationInstallation = $this->createInstallation(Uuid::v7());

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn($applicationInstallation);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('findByMemberId')
            ->with($command->memberId, Bitrix24AccountStatus::new, null, null)
            ->willReturn([]);

        $this->applicationInstallationRepository->expects($this->never())->method('save');
        $this->bitrix24AccountRepository->expects($this->never())->method('save');
        $this->flusher->expects($this->never())->method('flush');

        $this->expectException(Bitrix24AccountNotFoundException::class);

        $this->handler->handle($command);
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
