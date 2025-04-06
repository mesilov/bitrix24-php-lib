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
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
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
use Bitrix24\Lib\ApplicationInstallations\UseCase\Install\Handler;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
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
     * @throws  ApplicationInstallationNotFoundException
     */
    #[Test]
    public function testInstallNewApplicationInstallation(): void
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
                $applicationInstallationBuilder->getId(),
                $bitrix24AccountBuilder->getId(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment(),
                $bitrix24AccountBuilder->getId(),
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope()
            )
        );

        $applicationInstallation = $this->repository->getById($applicationInstallationBuilder->getId());

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();

        $this->assertContains('Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent', $dispatchedEvents);
        $this->assertContains('Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent', $dispatchedEvents);
        $this->assertContains('Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent', $dispatchedEvents);
        $this->assertContains('Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent', $dispatchedEvents);
        $this->assertEquals(ApplicationInstallationStatus::active, $applicationInstallation->getStatus());
        $this->assertEquals($bitrix24AccountBuilder->getId(), $applicationInstallation->getBitrix24AccountId());
    }
}
