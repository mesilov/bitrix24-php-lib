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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\UpdateVersion;

use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\MultipleBitrix24AccountsFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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
#[CoversClass(Bitrix24Accounts\UseCase\UpdateVersion\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Accounts\UseCase\UpdateVersion\Handler $handler;

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $this->eventDispatcher = new TraceableEventDispatcher($eventDispatcher, new Stopwatch());

        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Bitrix24Accounts\UseCase\UpdateVersion\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger(),);

    }

    #[Test]
    public function testSuccessUpdateVersion(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->handler->handle(new Bitrix24Accounts\UseCase\UpdateVersion\Command(
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getAuthToken(),
            2,
            new Scope(['crm','log'])
        ));

        $updated = $this->repository->getById($bitrix24Account->getId());

        $this->assertEquals(2,$updated->getApplicationVersion(), 'expected application version is 2');
        $this->assertEquals(new Scope(['crm','log']),$updated->getApplicationScope(), 'application Scope is not equal');
    }

    #[Test]
    public function testNotFoundBitrix24AccountForUpdateVersion(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(MultipleBitrix24AccountsFoundException::class);
        $this->expectExceptionMessage(sprintf('bitrix24account not found by memberId %s, status %s and bitrix24UserId %s ',
                $bitrix24Account->getMemberId(),
                'active',
                3558)
        );

        $this->handler->handle(new Bitrix24Accounts\UseCase\UpdateVersion\Command(
            $bitrix24Account->getId(),
            3558,
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getAuthToken(),
            2,
            new Scope(['crm','log'])
        ));
    }

    #[Test]
    public function testNotValidVersionForUpdateVersion(): void
    {
        $applicationToken = Uuid::v7()->toRfc4122();
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->build();


        $this->repository->save($bitrix24Account);
        $this->flusher->flush();


        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'you cannot downgrade application version or set some version, current version «%s», but you try to upgrade to «%s»',
                $bitrix24Account->getApplicationVersion(),
                '1'
            )
        );

        $this->handler->handle(new Bitrix24Accounts\UseCase\UpdateVersion\Command(
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getAuthToken(),
            1,
            new Scope(['crm','log'])
        ));
    }
}
