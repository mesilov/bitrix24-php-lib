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

namespace Bitrix24\Lib\Journal\ValueObjects;

use Darsyn\IP\Version\Multi as IP;

/**
 * Journal context value object
 */
readonly class JournalContext
{
    public function __construct(
        private string $label,
        private ?array $payload = null,
        private ?int $bitrix24UserId = null,
        private ?IP $ipAddress = null
    ) {
    }

    public function getLabel(): string
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

    public function getIpAddress(): ?IP
    {
        return $this->ipAddress;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'payload' => $this->payload,
            'bitrix24UserId' => $this->bitrix24UserId,
            'ipAddress' => $this->ipAddress?->getCompactedAddress(),
        ];
    }
}
