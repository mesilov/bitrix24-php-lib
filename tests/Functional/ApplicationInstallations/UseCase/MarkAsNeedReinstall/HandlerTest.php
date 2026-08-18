<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\MarkAsNeedReinstall;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkAsNeedReinstall\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\MarkAsNeedReinstall\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationMarkedNeedReinstallEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\LogicException;
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
    public function testPendingInstallationIsMarkedAsNeedReinstall(): void
    {
        $installation = $this->persistInstallation();

        $this->handler->handle(new Command($installation->getId(), 'installation timed out without ONAPPINSTALL'));
        $this->entityManager->clear();

        $updated = $this->installationRepository->getById($installation->getId());

        self::assertSame(ApplicationInstallationStatus::needReinstall, $updated->getStatus());
        self::assertSame('installation timed out without ONAPPINSTALL', $updated->getComment());

        $events = $this->eventDispatcher->getOrphanedEvents();
        self::assertContains(ApplicationInstallationMarkedNeedReinstallEvent::class, $events);
    }

    #[Test]
    public function testActiveInstallationThrowsLogicException(): void
    {
        $installation = $this->persistInstallation();
        $installation->applicationInstalled(Uuid::v7()->toRfc4122());
        $this->entityManager->flush();

        $this->expectException(LogicException::class);

        $this->handler->handle(new Command($installation->getId(), 'installation timed out without ONAPPINSTALL'));
    }

    #[Test]
    public function testUnknownInstallationIdThrowsNotFoundException(): void
    {
        $this->expectException(ApplicationInstallationNotFoundException::class);

        $this->handler->handle(new Command(Uuid::v7(), 'installation timed out without ONAPPINSTALL'));
    }

    private function persistInstallation(): ApplicationInstallation
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

        $installation = new ApplicationInstallation(
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

        $this->entityManager->persist($bitrix24Account);
        $this->entityManager->persist($installation);
        $this->entityManager->flush();

        return $installation;
    }
}
