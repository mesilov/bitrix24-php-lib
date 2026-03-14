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
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Darsyn\IP\Version\Multi as IP;
use Bitrix24\Lib\Services\Flusher;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Uid\Uuid;

/**
 * PSR-3 compatible journal logger
 * Writes log entries to the journal repository.
 */
class JournalLogger implements LoggerInterface
{
    use LoggerTrait;

    private const string DEFAULT_LABEL = 'application.log';

    public function __construct(
        private readonly string $memberId,
        private readonly Uuid $applicationInstallationId,
        private readonly JournalItemRepositoryInterface $repository,
        private readonly Flusher $flusher
    ) {}

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed                $level
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!is_string($level)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid log level type: %s', get_debug_type($level))
            );
        }

        $label = $context['label'] ?? self::DEFAULT_LABEL;
        $userId = $context['userId'] ?? null;
        $journalContext = $this->createContext($context);

        $journalItem = new JournalItem(
            memberId: $this->memberId,
            applicationInstallationId: $this->applicationInstallationId,
            level: strtolower($level),
            message: (string) $message,
            label: (string) $label,
            userId: $userId,
            context: $journalContext
        );

        $this->repository->save($journalItem);
        $this->flusher->flush();
    }

    /**
     * Create Context from PSR-3 context array.
     */
    private function createContext(array $context): Context
    {
        $ipAddress = null;
        if (isset($context['ipAddress']) && is_string($context['ipAddress'])) {
            try {
                $ipAddress = IP::factory($context['ipAddress']);
            } catch (\Throwable) {
                // Ignore invalid IP addresses
            }
        }

        return new Context(
            payload: $context['payload'] ?? null,
            bitrix24UserId: isset($context['bitrix24UserId']) ? (int) $context['bitrix24UserId'] : null,
            ipAddress: $ipAddress
        );
    }
}
