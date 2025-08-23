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

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\Install;


use Bitrix24\Lib\Bitrix24Accounts;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\ApplicationInstallations;
use Bitrix24\Lib\Tests\EntityManagerFactory;

use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
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
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Handler;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationInstallations\UseCase\Install\Handler::class)]
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
    public function testNewInstallation(): void
    {
        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->build();


        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();


        $this->handler->handle(
            new ApplicationInstallations\UseCase\Install\Command(
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope(),
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                null, // applicationToken
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment()
            )
        );

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();

        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent::class, $dispatchedEvents);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testNewInstallationWithToken(): void
    {
        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->build();


        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();

        $newApplicationToken = Uuid::v7()->toRfc4122();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\Install\Command(
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope(),
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                $newApplicationToken, // applicationToken
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment()
            )
        );


        $activeInstallation = $this->repository->findByApplicationToken($newApplicationToken);
        $this->assertNotNull($activeInstallation);

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();

        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent::class, $dispatchedEvents);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testNewInstallationWithEmptyToken(): void
    {
        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->build();


        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();

        $this->expectException(InvalidArgumentException::class);
        $this->handler->handle(
            new ApplicationInstallations\UseCase\Install\Command(
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope(),
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                '', // applicationToken
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment()
            )
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testReinstallInstallation(): void
    {

        $memberId = Uuid::v4()->toRfc4122();

        // Load account and application installation into database for reinstallation testing.
        $applicationToken = Uuid::v7()->toRfc4122();
        $currentBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->withMaster(true)
            ->build();


        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($currentBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->build();

        $this->bitrix24accountRepository->save($currentBitrix24Account);
        $this->repository->save($applicationInstallation);
        $this->flusher->flush();

        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withMemberId($memberId)
            ->build();

        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\Install\Command(
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope(),
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                null, // applicationToken
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment()
            )
        );

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();

        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent::class, $dispatchedEvents);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testFewInstallationsOnOneAccount(): void
    {

        $memberId = Uuid::v4()->toRfc4122();

        // Load account and application installation into database for reinstallation testing.
        $applicationToken = Uuid::v7()->toRfc4122();
        $currentBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->build();


        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($currentBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->build();

        $this->bitrix24accountRepository->save($currentBitrix24Account);
        $this->repository->save($applicationInstallation);
        $this->flusher->flush();

        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();

        $this->handler->handle(
            new ApplicationInstallations\UseCase\Install\Command(
                $currentBitrix24Account->getMemberId(),
                new Domain($currentBitrix24Account->getDomainUrl()),
                $currentBitrix24Account->getAuthToken(),
                $currentBitrix24Account->getApplicationVersion(),
                $currentBitrix24Account->getApplicationScope(),
                $currentBitrix24Account->getBitrix24UserId(),
                $currentBitrix24Account->isBitrix24UserAdmin(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                null, // applicationToken
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment()
            )
        );

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();

        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent::class, $dispatchedEvents);
    }

}
