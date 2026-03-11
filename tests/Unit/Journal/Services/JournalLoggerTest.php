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

use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Tests\Unit\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Uid\Uuid;

class JournalLoggerTest extends TestCase
{
    private InMemoryJournalItemRepository $repository;

    private EntityManagerInterface $entityManager;

    private Uuid $applicationInstallationId;

    private string $memberId;

    private JournalLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->applicationInstallationId = Uuid::v7();
        $this->memberId = 'test-member-id';

        $this->logger = new JournalLogger(
            $this->memberId,
            $this->applicationInstallationId,
            $this->repository,
            $this->entityManager
        );
    }

    public function testLogInfoMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->info('Test info message', ['label' => 'test.label', 'userId' => 'test-user']);

        $items = $this->repository->findAll();
        $this->assertCount(1, $items);
        $this->assertSame(LogLevel::INFO, $items[0]->getLevel());
        $this->assertSame($this->memberId, $items[0]->getMemberId());
        $this->assertSame('Test info message', $items[0]->getMessage());
        $this->assertSame('test.label', $items[0]->getLabel());
        $this->assertSame('test-user', $items[0]->getUserId());
    }

    public function testLogErrorMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->error('Test error message', ['label' => 'error.label']);

        $items = $this->repository->findAll();
        $this->assertCount(1, $items);
        $this->assertSame(LogLevel::ERROR, $items[0]->getLevel());
        $this->assertSame('error.label', $items[0]->getLabel());
    }

    public function testLogWarningMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->warning('Test warning message', ['label' => 'warning.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::WARNING, $items[0]->getLevel());
    }

    public function testLogDebugMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->debug('Test debug message', ['label' => 'debug.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::DEBUG, $items[0]->getLevel());
    }

    public function testLogEmergencyMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->emergency('Test emergency message', ['label' => 'emergency.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::EMERGENCY, $items[0]->getLevel());
    }

    public function testLogAlertMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->alert('Test alert message', ['label' => 'alert.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::ALERT, $items[0]->getLevel());
    }

    public function testLogCriticalMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->critical('Test critical message', ['label' => 'critical.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::CRITICAL, $items[0]->getLevel());
    }

    public function testLogNoticeMessage(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->notice('Test notice message', ['label' => 'notice.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::NOTICE, $items[0]->getLevel());
    }

    public function testLogWithContext(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $context = [
            'label' => 'test.label',
            'payload' => ['key' => 'value'],
            'bitrix24UserId' => 123,
            'ipAddress' => '192.168.1.1',
        ];

        $this->logger->info('Test message with context', $context);

        $items = $this->repository->findAll();
        $item = $items[0];

        $this->assertSame('test.label', $item->getLabel());
        $this->assertSame(['key' => 'value'], $item->getContext()->getPayload());
        $this->assertSame(123, $item->getContext()->getBitrix24UserId());
        $this->assertNotNull($item->getContext()->getIpAddress());
    }

    public function testLogWithoutLabelUsesDefault(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->info('Test message without label');

        $items = $this->repository->findAll();
        $this->assertSame('application.log', $items[0]->getLabel());
    }

    public function testLogMultipleMessages(): void
    {
        $this->entityManager->expects($this->exactly(3))->method('flush');

        $this->logger->info('Message 1', ['label' => 'test.label']);
        $this->logger->error('Message 2', ['label' => 'test.label']);
        $this->logger->debug('Message 3', ['label' => 'test.label']);

        $items = $this->repository->findAll();
        $this->assertCount(3, $items);
    }

    public function testLogAssignsCorrectApplicationInstallationId(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->info('Test message', ['label' => 'test.label']);

        $items = $this->repository->findAll();
        $this->assertTrue($items[0]->getApplicationInstallationId()->equals($this->applicationInstallationId));
    }

    public function testLogWithStringLevel(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->log('info', 'Test message', ['label' => 'test.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::INFO, $items[0]->getLevel());
    }

    public function testLogWithLogLevelEnum(): void
    {
        $this->entityManager->expects($this->once())->method('flush');

        $this->logger->log(LogLevel::ERROR, 'Test message', ['label' => 'test.label']);

        $items = $this->repository->findAll();
        $this->assertSame(LogLevel::ERROR, $items[0]->getLevel());
    }

    public function testLogWithInvalidLevelThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level type');

        $this->logger->log(123, 'Test message');
    }

    public function testLogWithInvalidStringLevelThrowsException(): void
    {
        $this->expectException(\Bitrix24\SDK\Core\Exceptions\InvalidArgumentException::class);

        $this->logger->log('invalid_level', 'Test message');
    }
}
