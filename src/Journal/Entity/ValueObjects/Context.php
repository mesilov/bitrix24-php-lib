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

namespace Bitrix24\Lib\Journal\Entity\ValueObjects;

use Darsyn\IP\Version\Multi as IP;

/**
 * Journal context value object.
 */
readonly class Context
{
    public function __construct(
        private ?array $payload = null,
        private ?int $bitrix24UserId = null,
        private ?IP $ipAddress = null
    ) {}

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
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'bitrix24UserId' => $this->bitrix24UserId,
            'ipAddress' => $this->ipAddress?->getCompactedAddress(),
        ];
    }

    /**
     * Returns whether this Context is equal to another.
     *
     * @param Context $other the Context to compare
     *
     * @return bool true if the Context are equal, false otherwise
     */
    public function equals(Context $other): bool
    {
        if ($this === $other) {
            return true;
        }

        return $this->payload === $other->payload
            && $this->bitrix24UserId === $other->bitrix24UserId
            && (
                null === $this->ipAddress
                ? null === $other->ipAddress
                : null !== $other->ipAddress && $this->ipAddress->equals($other->ipAddress)
            );
    }
}
