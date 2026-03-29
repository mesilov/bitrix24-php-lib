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

namespace Bitrix24\Lib\Journal\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Journal item entity
 * Each journal record contains domain business events for technical support staff.
 */
class JournalItem extends AggregateRoot implements JournalItemInterface
{
    private readonly Uuid $id;

    private readonly CarbonImmutable $createdAt;

    public function __construct(
        private readonly string $memberId,
        private readonly Uuid $applicationInstallationId,
        private readonly string $level,
        private readonly string $message,
        private readonly string $label,
        private readonly Context $context
    ) {
        $this->validate();
        $this->id = Uuid::v7();
        $this->createdAt = new CarbonImmutable();
    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[\Override]
    public function getApplicationInstallationId(): Uuid
    {
        return $this->applicationInstallationId;
    }

    #[\Override]
    public function getMemberId(): string
    {
        return $this->memberId;
    }

    #[\Override]
    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    #[\Override]
    public function getLevel(): string
    {
        return $this->level;
    }

    #[\Override]
    public function getMessage(): string
    {
        return $this->message;
    }

    #[\Override]
    public function getLabel(): string
    {
        return $this->label;
    }

    #[\Override]
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Returns whether this JournalItem is equal to another.
     *
     * @param JournalItemInterface $other the journalItem to compare
     *
     * @return bool true if the JournalItem are equal, false otherwise
     */
    public function equals(JournalItemInterface $other): bool
    {
        if ($this === $other) {
            return true;
        }

        return $this->getId()->equals($other->getId())
            && $this->getApplicationInstallationId()->equals($other->getApplicationInstallationId())
            && $this->getMemberId() === $other->getMemberId()
            && $this->createdAtToMilliseconds($this->getCreatedAt()) === $this->createdAtToMilliseconds($other->getCreatedAt())
            && $this->getLevel() === $other->getLevel()
            && $this->getMessage() === $other->getMessage()
            && $this->getLabel() === $other->getLabel()
            && $this->getContext()->equals($other->getContext());
    }

    private function createdAtToMilliseconds(CarbonImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    private function validate(): void
    {
        if ('' === trim($this->memberId)) {
            throw new InvalidArgumentException('memberId cannot be empty');
        }

        if ('' === trim($this->message)) {
            throw new InvalidArgumentException('Journal message cannot be empty');
        }

        if ('' === trim($this->label)) {
            throw new InvalidArgumentException('Journal label cannot be empty');
        }
    }
}
