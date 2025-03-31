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

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\InstallStart;


use Bitrix24\Lib\Bitrix24Accounts;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\ApplicationInstallations;
use Bitrix24\Lib\Tests\EntityManagerFactory;

use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
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
use Bitrix24\Lib\ApplicationInstallations\UseCase\InstallStart\Handler;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
/**
 * @internal
 */
#[CoversClass(ApplicationInstallations\UseCase\InstallStart\Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handlerApplicationInstallation;
    private Bitrix24Accounts\UseCase\InstallStart\Handler $handlerBitrix24Account;

    private Flusher $flusher;

    private ApplicationInstallationRepository $applicationInstallationRepository;

    private Bitrix24AccountRepository $bitrix24AccountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->applicationInstallationRepository = new ApplicationInstallationRepository($entityManager);
        $this->bitrix24AccountRepository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);

        $this->handlerApplicationInstallation = new Handler(
            $this->applicationInstallationRepository,
            $this->flusher,
            new NullLogger()
        );

        $this->handlerBitrix24Account = new Bitrix24Accounts\UseCase\InstallStart\Handler(
            $this->bitrix24AccountRepository,
            $this->flusher,
            new NullLogger()
        );

    }

    /**
     * @throws InvalidArgumentException
     * @throws  Bitrix24AccountNotFoundException|ApplicationInstallationNotFoundException
     */
    #[Test]
    public function testInstallNewApplicationInstallation(): void
    {

        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->build();

        $this->handlerBitrix24Account->handle(
            new Bitrix24Accounts\UseCase\InstallStart\Command(
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

        $bitrix24Account = $this->bitrix24AccountRepository->getById($bitrix24AccountBuilder->getId());

        $this->assertEquals($bitrix24Account->getId(), $bitrix24AccountBuilder->getId());


        $applicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build();

        $this->handlerApplicationInstallation->handle(
            new ApplicationInstallations\UseCase\InstallStart\Command(
                $applicationInstallationBuilder->getId(),
                $bitrix24AccountBuilder->getId(),
                $applicationInstallationBuilder->getApplicationStatus(),
                $applicationInstallationBuilder->getPortalLicenseFamily(),
                $applicationInstallationBuilder->getPortalUsersCount(),
                $applicationInstallationBuilder->getContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerContactPersonId(),
                $applicationInstallationBuilder->getBitrix24PartnerId(),
                $applicationInstallationBuilder->getExternalId(),
                $applicationInstallationBuilder->getComment()
            )
        );

        $applicationInstallation = $this->applicationInstallationRepository->getById($applicationInstallationBuilder->getId());

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        print_r($dispatchedEvents); // Выводит список событий

        $this->assertContains('Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent', $dispatchedEvents);
        $this->assertContains('Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent', $dispatchedEvents);
        $this->assertEquals(ApplicationInstallationStatus::new, $applicationInstallation->getStatus());
    }
}
