<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish\Command;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;
use PHPUnit\Framework\TestCase;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[Test]
    #[TestDox('test finish installation for Command')]
    public function testValidCommand(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $applicationToken = Uuid::v7()->toRfc4122();
        $command = new Command(
            $applicationToken,
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId()
        );
        $this->assertInstanceOf(Command::class, $command);
    }

    public function testEmptyApplicationToken(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application token cannot be empty.');

        new Command('',
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId()
        );
    }


    public function testEmptyMemberId(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member ID cannot be empty.');

        $applicationToken = Uuid::v7()->toRfc4122();
        new Command($applicationToken,
            '',
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getBitrix24UserId()
        );
    }

    public function testEmptyDomainUrl(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain URL cannot be empty.');

        $applicationToken = Uuid::v7()->toRfc4122();
        new Command($applicationToken,
            $bitrix24Account->getMemberId(),
            '',
            $bitrix24Account->getBitrix24UserId()
        );
    }

    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $this->eventDispatcher = new TraceableEventDispatcher($eventDispatcher, new Stopwatch());
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager,$this->eventDispatcher);
    }
}