<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\News\UseCase\RevertToDraft;

use Bitrix24\Lib\News\Entity\NewsStatus;
use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
use Bitrix24\Lib\News\Infrastructure\Doctrine\NewsRepository;
use Bitrix24\Lib\News\UseCase\RevertToDraft\Command;
use Bitrix24\Lib\News\UseCase\RevertToDraft\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\News\Builders\NewsBuilder;
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

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $paginator = new Paginator($eventDispatcher, $this->createStub(ArgumentAccessInterface::class));
        $this->repository = new NewsRepository($entityManager, $paginator);
        $this->flusher = new Flusher($entityManager, $eventDispatcher);

        $this->handler = new Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    public function testCanRevertPublishedNewsToDraft(): void
    {
        $news = (new NewsBuilder())
            ->withStatus(NewsStatus::published)
            ->build()
        ;
        $this->repository->save($news);
        $this->flusher->flush();
        EntityManagerFactory::get()->clear();

        $this->handler->handle(new Command($news->getId()));

        EntityManagerFactory::get()->clear();

        $loaded = $this->repository->getById($news->getId());
        self::assertSame(NewsStatus::draft, $loaded->getStatus());
    }

    public function testThrowsExceptionForNonExistentNews(): void
    {
        $this->expectException(NewsNotFoundException::class);

        $this->handler->handle(new Command(Uuid::v7()));
    }
}
