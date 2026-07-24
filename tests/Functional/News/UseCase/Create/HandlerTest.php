<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\News\UseCase\Create;

use Bitrix24\Lib\News\Entity\NewsStatus;
use Bitrix24\Lib\News\Infrastructure\Doctrine\NewsRepository;
use Bitrix24\Lib\News\UseCase\Create\Command;
use Bitrix24\Lib\News\UseCase\Create\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\News\Builders\NewsBuilder;
use Knp\Component\Pager\ArgumentAccess\ArgumentAccessInterface;
use Knp\Component\Pager\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    public function testCanCreateDraftNews(): void
    {
        $news = (new NewsBuilder())->build();

        $this->handler->handle(new Command($news->getTitle(), $news->getText()));

        EntityManagerFactory::get()->clear();

        $created = $this->repository->findByTitle($news->getTitle());

        self::assertCount(1, $created);
        self::assertSame($news->getTitle(), $created[0]->getTitle());
        self::assertSame($news->getText(), $created[0]->getText());
        self::assertSame(NewsStatus::draft, $created[0]->getStatus());
    }
}
