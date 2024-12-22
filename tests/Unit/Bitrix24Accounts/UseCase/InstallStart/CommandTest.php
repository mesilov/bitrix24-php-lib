<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallStart\Command;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;


    public function testValidBitrix24UserId(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 User ID must be a positive integer.');

        new Command(
            $bitrix24Account->getId(),
            0,
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getAuthToken(),
            $bitrix24Account->getApplicationVersion(),
            $bitrix24Account->getApplicationScope()
        );
    }

    public function testValidMemberId(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member ID must be a non-empty string.');

        new Command(
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            '',
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getAuthToken(),
            $bitrix24Account->getApplicationVersion(),
            $bitrix24Account->getApplicationScope()
        );
    }

    public function testValidDomainUrl(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain URL is not valid.');

        new Command(
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            '',
            $bitrix24Account->getAuthToken(),
            $bitrix24Account->getApplicationVersion(),
            $bitrix24Account->getApplicationScope()
        );
    }

    public function testValidApplicationVersion(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application version must be a positive integer.');

        new Command(
            $bitrix24Account->getId(),
            $bitrix24Account->getBitrix24UserId(),
            $bitrix24Account->isBitrix24UserAdmin(),
            $bitrix24Account->getMemberId(),
            $bitrix24Account->getDomainUrl(),
            $bitrix24Account->getAuthToken(),
            0,
            $bitrix24Account->getApplicationScope()
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