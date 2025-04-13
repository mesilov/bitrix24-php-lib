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

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\Reinstall;


use Bitrix24\Lib\Bitrix24Accounts;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\ApplicationInstallations;
use Bitrix24\Lib\Tests\EntityManagerFactory;

use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\Reinstall\Handler;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationInstallations\UseCase\Reinstall\Handler::class)]
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
     * @throws  ApplicationInstallationNotFoundException
     */
    #[Test]
    public function testReinstallApplicationInstallation(): void
    {

        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->bitrix24accountRepository->save($bitrix24Account);
        $this->flusher->flush();

        $oldApplicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->build();

        $this->repository->save($oldApplicationInstallationBuilder);
        $this->flusher->flush();

        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::new)
            ->build();


        $this->handler->handle(
            new ApplicationInstallations\UseCase\Reinstall\Command(
                $applicationInstallationBuilder->getId(),
                $applicationInstallationBuilder->getStatus(),
                $applicationInstallationBuilder->getBitrix24AccountId(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment(),
                $bitrix24AccountBuilder->getId(),
                $bitrix24AccountBuilder->getStatus(),
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope(),
                $applicationToken
            )
        );

        $applicationInstallation = $this->repository->getById($applicationInstallationBuilder->getId());

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        var_dump($dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationUninstalledEvent::class, $dispatchedEvents);

        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent::class, $dispatchedEvents);
        $this->assertContains(\Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationUninstalledEvent::class, $dispatchedEvents);
        $this->assertEquals(ApplicationInstallationStatus::active, $applicationInstallation->getStatus());
        $this->assertEquals($applicationInstallationBuilder->getBitrix24AccountId(), $applicationInstallation->getBitrix24AccountId());
    }
}
