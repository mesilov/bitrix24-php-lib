<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\News\UseCase\Publish;

use Bitrix24\Lib\News\Entity\NewsStatus;
use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
use Bitrix24\Lib\News\Infrastructure\Doctrine\NewsRepository;
use Bitrix24\Lib\News\UseCase\Publish\Command;
use Bitrix24\Lib\News\UseCase\Publish\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\News\Builders\NewsBuilder;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\ArgumentAccess\ArgumentAccessInterface;
use Knp\Component\Pager\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;

    private NewsRepository $repository;

    private Flusher $flusher;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $paginator = new Paginator($eventDispatcher, $this->createStub(ArgumentAccessInterface::class));
        $this->repository = new NewsRepository($this->entityManager, $paginator);
        $this->flusher = new Flusher($this->entityManager, $eventDispatcher);

        $this->handler = new Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    public function testCanPublishDraftNews(): void
    {
        $newsItem = (new NewsBuilder())->build();
        $this->repository->save($newsItem);
        $this->flusher->flush();
        $this->entityManager->clear();

        $this->handler->handle(new Command($newsItem->getId()));

        $this->entityManager->clear();

        $loaded = $this->repository->getById($newsItem->getId());
        self::assertSame(NewsStatus::published, $loaded->getStatus());
    }

    public function testThrowsExceptionForNonExistentNews(): void
    {
        $this->expectException(NewsNotFoundException::class);

        $this->handler->handle(new Command(Uuid::v7()));
    }

    public function testPublishWithProvidedTimestamp(): void
    {
        $newsItem = (new NewsBuilder())->build();
        $this->repository->save($newsItem);
        $this->flusher->flush();
        $this->entityManager->clear();

        $frozenTime = CarbonImmutable::parse('2025-01-15 10:30:00');
        $this->handler->handle(new Command($newsItem->getId(), $frozenTime));

        $this->entityManager->clear();

        $loaded = $this->repository->getById($newsItem->getId());
        self::assertEquals($frozenTime, $loaded->getUpdatedAt());
    }
}
