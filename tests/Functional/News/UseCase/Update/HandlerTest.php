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

    public function testCanUpdateTitleAndText(): void
    {
        $news = (new NewsBuilder())
            ->withTitle('Old title')
            ->withText('Old text')
            ->build()
        ;
        $this->repository->save($news);
        $this->flusher->flush();
        EntityManagerFactory::get()->clear();

        $this->handler->handle(new Command($news->getId(), 'New title', 'New text'));

        EntityManagerFactory::get()->clear();

        $loaded = $this->repository->getById($news->getId());
        self::assertSame('New title', $loaded->getTitle());
        self::assertSame('New text', $loaded->getText());
    }

    public function testCanAttachAndDetachImage(): void
    {
        $news = (new NewsBuilder())
            ->withImageUrl('https://example.com/old-image.png')
            ->build()
        ;
        $this->repository->save($news);
        $this->flusher->flush();
        EntityManagerFactory::get()->clear();

        $this->handler->handle(
            new Command($news->getId(), $news->getTitle(), $news->getText(), 'https://example.com/new-image.png')
        );

        EntityManagerFactory::get()->clear();

        $loaded = $this->repository->getById($news->getId());
        self::assertSame('https://example.com/new-image.png', $loaded->getImageUrl());

        // detach
        $this->handler->handle(
            new Command($news->getId(), $news->getTitle(), $news->getText(), null)
        );

        EntityManagerFactory::get()->clear();

        $loaded = $this->repository->getById($news->getId());
        self::assertNull($loaded->getImageUrl());
    }

    public function testThrowsExceptionForNonExistentNews(): void
    {
        $this->expectException(NewsNotFoundException::class);

        $this->handler->handle(new Command(Uuid::v7(), 'Title', 'Text'));
    }
}
