<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Journal\Services;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;
use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\ArgumentAccess\ArgumentAccessInterface;
use Knp\Component\Pager\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(JournalLogger::class)]
class HandlerTest extends TestCase
{
    private JournalLogger $logger;

    private Flusher $flusher;

    private DoctrineDbalJournalItemRepository $repository;

    private TraceableEventDispatcher $eventDispatcher;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $paginator = new Paginator($this->eventDispatcher, $this->createMock(ArgumentAccessInterface::class));
        $this->repository = new DoctrineDbalJournalItemRepository($this->entityManager, $paginator);
        $this->flusher = new Flusher($this->entityManager, $this->eventDispatcher);
        $this->logger = new JournalLogger(
            $this->repository,
            $this->flusher
        );
    }

    /**
     * This test verifies that a JournalItem can be created and
     * successfully persisted to the database.
     */
    #[Test]
    public function testSuccessAdd(): void
    {
        $memberId = 'test-member-id';
        $uuidV7 = Uuid::v7();
        $level = 'info';
        $message = 'Test message';
        $label = 'test-label';
        $context = new Context(['foo' => 'bar'], 123);

        $journalItem = new JournalItem(
            $memberId,
            $uuidV7,
            $level,
            $message,
            $label,
            $context
        );

        $this->logger->add($journalItem);

        $this->entityManager->clear();

        $savedItem = $this->repository->findById($journalItem->getId());

        $this->assertNotNull($savedItem);
        $this->assertTrue($journalItem->equals($savedItem));
    }
}
