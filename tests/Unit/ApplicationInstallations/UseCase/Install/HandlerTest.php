<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Tests\Helpers\ApplicationInstallations\RecordingApplicationInstallationInMemoryRepository;
use Bitrix24\Lib\Tests\Helpers\Bitrix24Accounts\RecordingBitrix24AccountInMemoryRepository;
use Bitrix24\Lib\Tests\Helpers\SpyFlusher;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
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
    private RecordingBitrix24AccountInMemoryRepository $bitrix24AccountRepository;

    private RecordingApplicationInstallationInMemoryRepository $applicationInstallationRepository;

    private SpyFlusher $flusher;

    private Handler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->bitrix24AccountRepository = new RecordingBitrix24AccountInMemoryRepository(new NullLogger());
        $this->applicationInstallationRepository = new RecordingApplicationInstallationInMemoryRepository(
            $this->bitrix24AccountRepository
        );
        $this->flusher = new SpyFlusher();

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

        $this->handler->handle($command);

        $bitrix24Accounts = $this->bitrix24AccountRepository->findByMemberId($command->memberId);
        self::assertCount(1, $bitrix24Accounts);

        $bitrix24Account = $bitrix24Accounts[0];
        $applicationInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

        self::assertNotNull($applicationInstallation);
        self::assertSame(Bitrix24AccountStatus::new, $bitrix24Account->getStatus());
        self::assertSame(ApplicationInstallationStatus::new, $applicationInstallation->getStatus());
        self::assertTrue($applicationInstallation->getBitrix24AccountId()->equals($bitrix24Account->getId()));
        self::assertSame(1, $this->bitrix24AccountRepository->getSaveCalls());
        self::assertSame(1, $this->applicationInstallationRepository->getSaveCalls());
        self::assertCount(1, $this->flusher->getFlushCalls());
        self::assertSame([$applicationInstallation, $bitrix24Account], $this->flusher->getFlushCalls()[0]);
    }

    #[Test]
    public function testHandleWithTokenFinishesInstallationInOneStep(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $command = $this->createCommand($applicationToken);

        $this->handler->handle($command);

        $bitrix24Accounts = $this->bitrix24AccountRepository->findByMemberId($command->memberId);
        self::assertCount(1, $bitrix24Accounts);

        $bitrix24Account = $bitrix24Accounts[0];
        $applicationInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

        self::assertNotNull($applicationInstallation);
        self::assertSame(Bitrix24AccountStatus::active, $bitrix24Account->getStatus());
        self::assertSame(ApplicationInstallationStatus::active, $applicationInstallation->getStatus());
        self::assertTrue($bitrix24Account->isApplicationTokenValid($applicationToken));
        self::assertTrue($applicationInstallation->isApplicationTokenValid($applicationToken));
        self::assertSame(1, $this->bitrix24AccountRepository->getSaveCalls());
        self::assertSame(1, $this->applicationInstallationRepository->getSaveCalls());
        self::assertCount(1, $this->flusher->getFlushCalls());
        self::assertSame([$applicationInstallation, $bitrix24Account], $this->flusher->getFlushCalls()[0]);
    }

    #[Test]
    public function testHandleReinstallOverPendingInstallationDeletesOldPairBeforeCreatingNewOne(): void
    {
        $command = $this->createCommand();
        $existingAccount = $this->createAccount($command->memberId, true);
        $applicationInstallation = $this->createInstallation($existingAccount->getId());

        $this->bitrix24AccountRepository->save($existingAccount);
        $this->applicationInstallationRepository->save($applicationInstallation);

        $bitrix24AccountSaveCalls = $this->bitrix24AccountRepository->getSaveCalls();
        $applicationInstallationSaveCalls = $this->applicationInstallationRepository->getSaveCalls();

        $this->handler->handle($command);

        $bitrix24Accounts = $this->bitrix24AccountRepository->findByMemberId($command->memberId);
        self::assertCount(2, $bitrix24Accounts);
        self::assertSame(Bitrix24AccountStatus::deleted, $existingAccount->getStatus());
        self::assertSame(ApplicationInstallationStatus::deleted, $applicationInstallation->getStatus());
        self::assertSame('reinstall before finish', $applicationInstallation->getComment());

        $activeInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);
        self::assertNotNull($activeInstallation);
        self::assertSame(ApplicationInstallationStatus::new, $activeInstallation->getStatus());

        $newBitrix24Account = null;
        foreach ($bitrix24Accounts as $bitrix24Account) {
            if (Bitrix24AccountStatus::new === $bitrix24Account->getStatus()) {
                $newBitrix24Account = $bitrix24Account;

                break;
            }
        }

        self::assertInstanceOf(Bitrix24AccountInterface::class, $newBitrix24Account);
        self::assertSame(Bitrix24AccountStatus::new, $newBitrix24Account->getStatus());
        self::assertTrue($activeInstallation->getBitrix24AccountId()->equals($newBitrix24Account->getId()));
        self::assertSame($bitrix24AccountSaveCalls + 2, $this->bitrix24AccountRepository->getSaveCalls());
        self::assertSame($applicationInstallationSaveCalls + 2, $this->applicationInstallationRepository->getSaveCalls());
        self::assertCount(2, $this->flusher->getFlushCalls());
        self::assertSame([$applicationInstallation, $existingAccount], $this->flusher->getFlushCalls()[0]);
        self::assertSame([$activeInstallation, $newBitrix24Account], $this->flusher->getFlushCalls()[1]);
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
