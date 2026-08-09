<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\News\UseCase\Update;

use Bitrix24\Lib\News\Exceptions\NewsNotFoundException;
use Bitrix24\Lib\News\Infrastructure\Doctrine\NewsRepository;
use Bitrix24\Lib\News\UseCase\Update\Command;
use Bitrix24\Lib\News\UseCase\Update\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\News\Builders\NewsBuilder;
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

    public function testCanUpdateTitleAndText(): void
    {
        $newsItem = (new NewsBuilder())
            ->withTitle('Old title')
            ->withText('Old text')
            ->build()
        ;
        $this->repository->save($newsItem);
        $this->flusher->flush();
        $this->entityManager->clear();

        $this->handler->handle(new Command($newsItem->getId(), 'New title', 'New text'));

        $this->entityManager->clear();

        $loaded = $this->repository->getById($newsItem->getId());
        self::assertSame('New title', $loaded->getTitle());
        self::assertSame('New text', $loaded->getText());
    }

    public function testCanChangeImage(): void
    {
        $newsItem = (new NewsBuilder())
            ->withImageUrl('https://example.com/old-image.png')
            ->build()
        ;
        $this->repository->save($newsItem);
        $this->flusher->flush();
        $this->entityManager->clear();

        $this->handler->handle(
            new Command($newsItem->getId(), $newsItem->getTitle(), $newsItem->getText(), 'https://example.com/new-image.png')
        );

        $this->entityManager->clear();

        $loaded = $this->repository->getById($newsItem->getId());
        self::assertSame('https://example.com/new-image.png', $loaded->getImageUrl());
    }

    public function testCanDetachImage(): void
    {
        $newsItem = (new NewsBuilder())
            ->withImageUrl('https://example.com/image.png')
            ->build()
        ;
        $this->repository->save($newsItem);
        $this->flusher->flush();
        $this->entityManager->clear();

        $this->handler->handle(
            new Command($newsItem->getId(), $newsItem->getTitle(), $newsItem->getText())
        );

        $this->entityManager->clear();

        $loaded = $this->repository->getById($newsItem->getId());
        self::assertNull($loaded->getImageUrl());
    }

    public function testThrowsExceptionForNonExistentNews(): void
    {
        $this->expectException(NewsNotFoundException::class);

        $this->handler->handle(new Command(Uuid::v7(), 'Title', 'Text'));
    }
}
