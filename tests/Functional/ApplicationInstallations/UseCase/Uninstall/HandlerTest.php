<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\Uninstall;


use Bitrix24\Lib\Bitrix24Accounts;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\ApplicationInstallations;
use Bitrix24\Lib\Tests\EntityManagerFactory;

use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Handler;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Symfony\Component\Uid\Uuid;


/**
 * @internal
 */
#[CoversClass(ApplicationInstallations\UseCase\Uninstall\Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;

    private Flusher $flusher;

    private ApplicationInstallationRepository $repository;

    private Bitrix24AccountRepository $bitrix24accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ApplicationInstallationRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->bitrix24accountRepository = new Bitrix24AccountRepository($entityManager);
        $this->handler = new Handler(
            $this->bitrix24accountRepository,
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testUninstall(): void
    {
        // Load account and application installation into database for uninstallation.
        $applicationToken = Uuid::v7()->toRfc4122();
        $oldBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMaster(true)
            ->withSetToken()
            ->withInstalled()
            ->build();

        $this->bitrix24accountRepository->save($oldBitrix24Account);

        $oldApplicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($oldBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->repository->save($oldApplicationInstallation);

        $this->flusher->flush();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\Uninstall\Command(
                new Domain($oldBitrix24Account->getDomainUrl()),
                $oldBitrix24Account->getMemberId(),
                $applicationToken
            )
        );

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationUninstalledEvent::class, $dispatchedEvents);

        $this->expectException(ApplicationInstallationNotFoundException::class);
        $this->repository->getById($oldApplicationInstallation->getId());
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testUninstallWithNotValidToken(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $oldBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMaster(true)
            ->withSetToken()
            ->withInstalled()
            ->build();

        $this->bitrix24accountRepository->save($oldBitrix24Account);

        $oldApplicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($oldBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->repository->save($oldApplicationInstallation);

        $this->flusher->flush();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\Uninstall\Command(
                new Domain($oldBitrix24Account->getDomainUrl()),
                $oldBitrix24Account->getMemberId(),
                'testNotValidToken'
            )
        );

        $applicationInstallation = $this->repository->getById($oldApplicationInstallation->getId());

        $this->assertEquals(ApplicationInstallationStatus::active, $applicationInstallation->getStatus());
        $this->assertEquals($oldApplicationInstallation->getUpdatedAt(), $applicationInstallation->getUpdatedAt());

        $bitrix24Account = $this->bitrix24accountRepository->getById($oldBitrix24Account->getId());

        $this->assertEquals(Bitrix24AccountStatus::active, $bitrix24Account->getStatus());
        $this->assertEquals($oldBitrix24Account->getUpdatedAt(), $bitrix24Account->getUpdatedAt());
    }

    public function testUninstallWithFewAccount(): void
    {
        $memberId = Uuid::v4()->toRfc4122();
        // Load account and application installation into database for uninstallation.
        $applicationToken = Uuid::v7()->toRfc4122();
        $oldBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMaster(true)
            ->withMemberId($memberId)
            ->withSetToken()
            ->build();

        $this->bitrix24accountRepository->save($oldBitrix24Account);

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withMemberId($memberId)
            ->build();

        $this->bitrix24accountRepository->save($bitrix24Account);

        $oldApplicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($oldBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->repository->save($oldApplicationInstallation);

        $this->flusher->flush();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\Uninstall\Command(
                new Domain($oldBitrix24Account->getDomainUrl()),
                $oldBitrix24Account->getMemberId(),
                $applicationToken
            )
        );

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();

        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationUninstalledEvent::class, $dispatchedEvents);

        $applicationInstallation = $this->repository->find($oldApplicationInstallation->getId());

        $this->assertEquals(ApplicationInstallationStatus::deleted, $applicationInstallation->getStatus());

        $bitrix24Accounts = $this->bitrix24accountRepository->findByMemberId($memberId);

        foreach ($bitrix24Accounts as $bitrix24Account) {
            $this->assertSame(
                Bitrix24AccountStatus::deleted,
                $bitrix24Account->getStatus(),
                sprintf('Account %s is not in "deleted" status', $bitrix24Account->getId())
            );
        }
    }


}