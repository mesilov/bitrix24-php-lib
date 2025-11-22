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

namespace Bitrix24\Lib\Journal\Services;

use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Uid\Uuid;

/**
 * PSR-3 compatible journal logger
 * Writes log entries to the journal repository
 */
class JournalLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly Uuid $applicationInstallationId,
        private readonly JournalItemRepositoryInterface $repository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Logs with an arbitrary level
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $logLevel = $this->convertLevel($level);

        $journalItem = JournalItem::create(
            applicationInstallationId: $this->applicationInstallationId,
            level: $logLevel,
            message: (string) $message,
            context: $context
        );

        $this->repository->save($journalItem);
        $this->entityManager->flush();
    }

    /**
     * Convert PSR-3 log level to LogLevel enum
     */
    private function convertLevel(mixed $level): LogLevel
    {
        if ($level instanceof LogLevel) {
            return $level;
        }

        if (is_string($level)) {
            return LogLevel::fromPsr3Level($level);
        }

        throw new \InvalidArgumentException(
            sprintf('Invalid log level type: %s', get_debug_type($level))
        );
    }
}
