<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Journal\MessageHandler;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Events\TestJournalEvent;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for TestJournalEvent
 * Writes event to journal with INFO level
 */
#[AsMessageHandler]
readonly class TestJournalEventHandler
{
    public function __construct(
        private JournalItemRepositoryInterface $journalItemRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(TestJournalEvent $event): void
    {
        // Create journal item with INFO level using PSR-3 factory method
        $journalItem = JournalItem::info(
            applicationInstallationId: $event->getApplicationInstallationId(),
            message: $event->getMessage(),
            context: [
                'label' => $event->getLabel(),
                'payload' => $event->getPayload(),
                'bitrix24UserId' => $event->getBitrix24UserId(),
                'ipAddress' => $event->getIpAddress(),
            ]
        );

        $this->journalItemRepository->save($journalItem);
        $this->entityManager->flush();
    }
}
