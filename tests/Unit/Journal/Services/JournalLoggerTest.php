<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Journal\Services;

use Darsyn\IP\Version\Multi as IP;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\Unit\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
class JournalLoggerTest extends TestCase
{
    private InMemoryJournalItemRepository $repository;

    private EntityManagerInterface $entityManager;

    private TraceableEventDispatcher $eventDispatcher;

    private JournalLogger $logger;

    private Flusher $flusher;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->flusher = new Flusher($this->entityManager, $this->eventDispatcher);

        $this->logger = new JournalLogger(
            $this->repository,
            $this->flusher
        );
    }

    public function testAddJournalItem(): void
    {
        $journalItem = new JournalItem(
            memberId: 'test-member-id',
            applicationInstallationId: Uuid::v7(),
            level: LogLevel::INFO,
            message: 'Test message',
            label: 'test.label',
            context: new Context(IP::factory('127.0.0.1'))
        );

        $this->logger->add($journalItem);

        $savedItem = $this->repository->getById($journalItem->getId());

        $this->assertTrue($journalItem->equals($savedItem));
    }
}
