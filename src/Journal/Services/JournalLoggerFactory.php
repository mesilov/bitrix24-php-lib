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

use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Bitrix24\Lib\Services\Flusher;

/**
 * Factory for creating JournalLogger instances.
 */
readonly class JournalLoggerFactory
{
    public function __construct(
        private JournalItemRepositoryInterface $repository,
        private Flusher $flusher
    ) {}

    /**
     * Create logger for specific application installation.
     */
    public function createLogger(string $memberId, Uuid $applicationInstallationId): LoggerInterface
    {
        return new JournalLogger(
            memberId: $memberId,
            applicationInstallationId: $applicationInstallationId,
            repository: $this->repository,
            flusher: $this->flusher
        );
    }
}
