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
        //Загружаем в базу данных аккаунт и установку приложения для их деинсталяции.
        $applicationToken = Uuid::v7()->toRfc4122();
        $oldBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMaster(true)
            ->withSetToken()
            ->build();


        $oldApplicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($oldBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->bitrix24accountRepository->save($oldBitrix24Account);
        $this->repository->save($oldApplicationInstallationBuilder);
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
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testUninstallWithNotValidToken()
    {
        //Загружаем в базу данных аккаунт и установку приложения для их деинсталяции.
        $applicationToken = Uuid::v7()->toRfc4122();
        $oldBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMaster(true)
            ->withSetToken()
            ->build();


        $oldApplicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($oldBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->bitrix24accountRepository->save($oldBitrix24Account);
        $this->repository->save($oldApplicationInstallationBuilder);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('application token «testNotValidToken» mismatch with application token «'. $applicationToken .'» for application installation «'.$oldApplicationInstallationBuilder->getId()->toRfc4122() .'»');
        $this->handler->handle(
            new ApplicationInstallations\UseCase\Uninstall\Command(
                new Domain($oldBitrix24Account->getDomainUrl()),
                $oldBitrix24Account->getMemberId(),
                'testNotValidToken'
            )
        );
    }

    public function testUninstallWithFewAccount()
    {
        $memberId = Uuid::v4()->toRfc4122();
        //Загружаем в базу данных аккаунт и установку приложения для их деинсталяции.
        $applicationToken = Uuid::v7()->toRfc4122();
        $oldBitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMaster(true)
            ->withMemberId($memberId)
            ->withSetToken()
            ->build();


        $oldBitrix24Account2 = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withMemberId($memberId)
            ->build();

        $oldApplicationInstallationBuilder = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($oldBitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->bitrix24accountRepository->save($oldBitrix24Account);
        $this->bitrix24accountRepository->save($oldBitrix24Account2);

        $this->repository->save($oldApplicationInstallationBuilder);
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
    }


}