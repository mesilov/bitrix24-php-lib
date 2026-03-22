<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Journal\Services\UseCase\Add;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;
use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Core\Response\DTO\Pagination;
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

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $paginator = new Paginator($this->eventDispatcher, $this->createMock(ArgumentAccessInterface::class));
        $this->repository = new DoctrineDbalJournalItemRepository($entityManager, $paginator);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->logger = new JournalLogger(
            $this->repository,
            $this->flusher
        );
    }

    #[Test]
    public function testSuccessAdd(): void
    {
        $memberId = 'test-member-id';
        $uuidV7 = Uuid::v7();
        $level = 'info';
        $message = 'Test message';
        $label = 'test-label';
        $userId = '123';
        $context = new Context(['foo' => 'bar'], 123);

        $journalItem = new JournalItem(
            $memberId,
            $uuidV7,
            $level,
            $message,
            $label,
            $userId,
            $context
        );

        $this->logger->add($journalItem);

        $savedItem = $this->repository->findById($journalItem->getId());

        $this->assertNotNull($savedItem);
        $this->assertEquals($journalItem->getId(), $savedItem->getId());
        $this->assertEquals($memberId, $savedItem->getMemberId());
        $this->assertEquals($uuidV7->toRfc4122(), $savedItem->getApplicationInstallationId()->toRfc4122());
        $this->assertEquals($level, $savedItem->getLevel());
        $this->assertEquals($message, $savedItem->getMessage());
        $this->assertEquals($label, $savedItem->getLabel());
        $this->assertEquals($userId, $savedItem->getUserId());
        $this->assertEquals($context->getPayload(), $savedItem->getContext()->getPayload());
        $this->assertEquals($context->getBitrix24UserId(), $savedItem->getContext()->getBitrix24UserId());
    }
}
