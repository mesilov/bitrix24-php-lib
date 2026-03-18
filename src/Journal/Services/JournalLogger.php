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

use Bitrix24\Lib\Journal\Entity\JournalItemInterface;
use Bitrix24\Lib\Journal\Infrastructure\JournalItemRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;

/**
 * Journal logger
 * Writes log entries to the journal repository.
 */
class JournalLogger
{

    public function __construct(
        private readonly JournalItemRepositoryInterface $repository,
        private readonly Flusher $flusher
    ) {}

    /**
     * @param JournalItemInterface $journalItem
     */
    public function add(JournalItemInterface $journalItem): void
    {
        $this->repository->save($journalItem);
        $this->flusher->flush();
    }
}
