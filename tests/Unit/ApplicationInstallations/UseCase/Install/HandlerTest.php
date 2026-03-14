<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

    private Handler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->bitrix24AccountRepository = $this->createMock(Bitrix24AccountRepositoryInterface::class);
        $this->applicationInstallationRepository = $this->createMock(ApplicationInstallationRepositoryInterface::class);
        $this->flusher = $this->createMock(Flusher::class);

        $this->handler = new Handler(
            $this->bitrix24AccountRepository,
            $this->applicationInstallationRepository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    public function testHandleWithoutTokenCreatesPendingEntities(): void
    {
        $command = $this->createCommand();
        $savedEntities = [];

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn(null);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Bitrix24Account $bitrix24Account) use (&$savedEntities): void {
                $savedEntities['account'] = $bitrix24Account;
            });

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (ApplicationInstallation $applicationInstallation) use (&$savedEntities): void {
                $savedEntities['installation'] = $applicationInstallation;
            });

        $this->flusher
            ->expects($this->once())
            ->method('flush')
            ->with(
                $this->callback(static fn (ApplicationInstallation $applicationInstallation): bool => $applicationInstallation->getStatus() === ApplicationInstallationStatus::new),
                $this->callback(static fn (Bitrix24Account $bitrix24Account): bool => $bitrix24Account->getStatus() === Bitrix24AccountStatus::new)
            );

        $this->handler->handle($command);

        self::assertArrayHasKey('account', $savedEntities);
        self::assertArrayHasKey('installation', $savedEntities);
        self::assertSame(Bitrix24AccountStatus::new, $savedEntities['account']->getStatus());
        self::assertSame(ApplicationInstallationStatus::new, $savedEntities['installation']->getStatus());
    }

    #[Test]
    public function testHandleWithTokenFinishesInstallationInOneStep(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand($applicationToken);
        $savedEntities = [];

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn(null);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Bitrix24Account $bitrix24Account) use (&$savedEntities): void {
                $savedEntities['account'] = $bitrix24Account;
            });

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (ApplicationInstallation $applicationInstallation) use (&$savedEntities): void {
                $savedEntities['installation'] = $applicationInstallation;
            });

        $this->flusher
            ->expects($this->once())
            ->method('flush')
            ->with(
                $this->callback(static fn(ApplicationInstallation $applicationInstallation): bool => $applicationInstallation->getStatus() === ApplicationInstallationStatus::active
                    && $applicationInstallation->isApplicationTokenValid($applicationToken)),
                $this->callback(static fn(Bitrix24Account $bitrix24Account): bool => $bitrix24Account->getStatus() === Bitrix24AccountStatus::active
                    && $bitrix24Account->isApplicationTokenValid($applicationToken))
            );

        $this->handler->handle($command);

        self::assertSame(Bitrix24AccountStatus::active, $savedEntities['account']->getStatus());
        self::assertSame(ApplicationInstallationStatus::active, $savedEntities['installation']->getStatus());
        self::assertTrue($savedEntities['account']->isApplicationTokenValid($applicationToken));
        self::assertTrue($savedEntities['installation']->isApplicationTokenValid($applicationToken));
    }

    #[Test]
    public function testHandleReinstallOverPendingInstallationDeletesOldPairBeforeCreatingNewOne(): void
    {
        $command = $this->createCommand();
        $bitrix24Account = $this->createAccount($command->memberId, true);
        $applicationInstallation = $this->createInstallation($bitrix24Account->getId());
        $flushCalls = [];

        $this->applicationInstallationRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (ApplicationInstallation $applicationInstallation): void {});

        $this->bitrix24AccountRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(static function (Bitrix24Account $bitrix24Account): void {});

        $this->applicationInstallationRepository
            ->expects($this->once())
            ->method('findByBitrix24AccountMemberId')
            ->with($command->memberId)
            ->willReturn($applicationInstallation);

        $this->bitrix24AccountRepository
            ->expects($this->once())
            ->method('findByMemberId')
            ->with($command->memberId)
            ->willReturn([$bitrix24Account]);

        $this->flusher
            ->expects($this->exactly(2))
            ->method('flush')
            ->willReturnCallback(static function (...$entities) use (&$flushCalls): void {
                $flushCalls[] = $entities;
            });

        $this->handler->handle($command);

        self::assertCount(2, $flushCalls);
        self::assertSame(ApplicationInstallationStatus::deleted, $applicationInstallation->getStatus());
        self::assertSame('reinstall before finish', $applicationInstallation->getComment());
        self::assertSame(Bitrix24AccountStatus::deleted, $bitrix24Account->getStatus());
        self::assertSame($applicationInstallation, $flushCalls[0][0]);
        self::assertSame($bitrix24Account, $flushCalls[0][1]);
        self::assertSame(ApplicationInstallationStatus::new, $flushCalls[1][0]->getStatus());
        self::assertSame(Bitrix24AccountStatus::new, $flushCalls[1][1]->getStatus());
    }

    private function createCommand(?string $applicationToken = null): Command
    {
        return new Command(
            memberId: Uuid::v4()->toRfc4122(),
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
