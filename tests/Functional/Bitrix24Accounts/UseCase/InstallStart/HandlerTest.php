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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\UnknownScopeCodeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Random\RandomException;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
/**
 * @internal
 */
#[CoversClass(Bitrix24Accounts\UseCase\InstallStart\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Accounts\UseCase\InstallStart\Handler $handler;

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager,$this->eventDispatcher);
        $this->handler = new Bitrix24Accounts\UseCase\InstallStart\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws Bitrix24AccountNotFoundException
     * @throws RandomException
     * @throws UnknownScopeCodeException
     */
    #[Test]
    public function testInstallStartHappyPath(): void
    {
        $uuidV7 = Uuid::v7();
        $b24UserId = random_int(1, 100_000);
        $isB24UserAdmin = true;
        $b24MemberId = Uuid::v7()->toRfc4122();
        $b24DomainUrl = Uuid::v7()->toRfc4122().'-test.bitrix24.com';
        $authToken = new AuthToken('old_1', 'old_2', 3600);
        $appVersion = 1;
        $scope = new Scope(['crm']);
        $this->handler->handle(
            new Bitrix24Accounts\UseCase\InstallStart\Command(
                $uuidV7,
                $b24UserId,
                $isB24UserAdmin,
                $b24MemberId,
                $b24DomainUrl,
                $authToken,
                $appVersion,
                $scope
            )
        );

        $bitrix24Account = $this->repository->getById($uuidV7);

        $this->assertEquals(
            $b24UserId,
            $bitrix24Account->getBitrix24UserId(),
            sprintf(
                'Expected the property value to be "%s", but got "%s"',
                $b24UserId,
                $bitrix24Account->getBitrix24UserId()
            )
        );

        $this->assertEquals(
            $isB24UserAdmin,
            $bitrix24Account->isBitrix24UserAdmin(),
            sprintf(
                'Expected the property value to be "%s", but got "%s"',
                $isB24UserAdmin,
                $bitrix24Account->isBitrix24UserAdmin()
            )
        );

        $this->assertEquals(
            $b24MemberId,
            $bitrix24Account->getMemberId(),
            sprintf(
                'Expected the property value to be "%s", but got "%s"',
                $b24MemberId,
                $bitrix24Account->getMemberId()
            )
        );

        $this->assertEquals(
            $b24DomainUrl,
            $bitrix24Account->getDomainUrl(),
            sprintf(
                'Expected the property value to be "%s", but got "%s"',
                $b24DomainUrl,
                $bitrix24Account->getDomainUrl()
            )
        );

        $this->assertEquals(
            $authToken,
            $bitrix24Account->getAuthToken(),
            'Object not equals'
        );

        $this->assertEquals(
            $appVersion,
            $bitrix24Account->getApplicationVersion(),
            sprintf(
                'Expected the property value to be "%s", but got "%s"',
                $appVersion,
                $bitrix24Account->getApplicationVersion()
            )
        );
        $this->assertEquals(
            $scope,
            $bitrix24Account->getApplicationScope(),
            'Object not equals'
        );

        $this->assertEquals(Bitrix24AccountStatus::new,$bitrix24Account->getStatus());

        $this->assertContains(
            Bitrix24AccountCreatedEvent::class,
            $this->eventDispatcher->getOrphanedEvents(),
            sprintf(
                'not found expected domain event «%s»',
                Bitrix24AccountCreatedEvent::class
            )
        );
    }

    #[Test]
    public function testCreateExistingAccount(): void
    {
        $uuidV7 = Uuid::v7();
        $b24UserId = random_int(1, 100_000);
        $isB24UserAdmin = true;
        $b24MemberId = Uuid::v7()->toRfc4122();
        $b24DomainUrl = 'https://'.Uuid::v7()->toRfc4122().'-test.bitrix24.com';
        $authToken = new AuthToken('old_1', 'old_2', 3600);
        $appVersion = 1;
        $scope = new Scope(['crm']);
        $this->handler->handle(
            new Bitrix24Accounts\UseCase\InstallStart\Command(
                $uuidV7,
                $b24UserId,
                $isB24UserAdmin,
                $b24MemberId,
                $b24DomainUrl,
                $authToken,
                $appVersion,
                $scope
            )
        );

        $this->handler->handle(
            new Bitrix24Accounts\UseCase\InstallStart\Command(
                $uuidV7,
                $b24UserId,
                $isB24UserAdmin,
                $b24MemberId,
                $b24DomainUrl,
                $authToken,
                $appVersion,
                $scope
            )
        );

        $accounts = $this->repository->find(['id' => $uuidV7]);

        if ($accounts instanceof Bitrix24AccountInterface) {
            // Если это один объект, количество будет 1
            $count = 1;
        } else {
            // Если ничего не найдено, количество будет 0
            $count = 0;
        }

        $this->assertEquals(1, $count);

    }
}
