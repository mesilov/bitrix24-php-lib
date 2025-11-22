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

namespace Bitrix24\Lib\Journal\Events;

use Symfony\Component\Uid\Uuid;

/**
 * Test event for journal demonstration
 * This event will be logged with INFO level
 */
readonly class TestJournalEvent
{
    public function __construct(
        private Uuid $applicationInstallationId,
        private string $message,
        private ?string $label = null,
        private ?array $payload = null,
        private ?int $bitrix24UserId = null,
        private ?string $ipAddress = null
    ) {
    }

    public function getApplicationInstallationId(): Uuid
    {
        return $this->applicationInstallationId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getBitrix24UserId(): ?int
    {
        return $this->bitrix24UserId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }
}
