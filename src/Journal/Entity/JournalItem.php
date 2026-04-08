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
        private readonly LogLevel $level,
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
    public function getLevel(): LogLevel
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
     * Now we use this method only for testing purposes.
     *
     * Compares creation timestamps without microseconds to avoid false mismatches
     * between in-memory objects and persisted database values.
     *
     * @param JournalItemInterface $other the journalItem to compare
     *
     * @return bool true if the JournalItem are equal, false otherwise
     */
    public function equals(JournalItemInterface $other): bool
    {
        return $this->getId()->equals($other->getId())
            && $this->getApplicationInstallationId()->equals($other->getApplicationInstallationId())
            && $this->getMemberId() === $other->getMemberId()
            && $this->getLevel() === $other->getLevel()
            && $this->getMessage() === $other->getMessage()
            && $this->getLabel() === $other->getLabel()
            && $this->getContext()->equals($other->getContext())
            && $this->normalizeCreatedAt($this->getCreatedAt())->equalTo($this->normalizeCreatedAt($other->getCreatedAt()));
    }

    /**
     * Normalizes the timestamp for comparison by removing microseconds.
     */
    private function normalizeCreatedAt(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setMicro(0);
    }

    /**
     * Validate a label against Kubernetes naming rules:
     * it must be 63 characters or fewer, start and end with an alphanumeric character,
     * and may contain only letters, digits, dots, underscores, and hyphens in between.
     */
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

        if (strlen($this->label) > 63) {
            throw new InvalidArgumentException('Journal label must not be longer than 63 characters');
        }

        if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?$/', $this->label)) {
            throw new InvalidArgumentException(
                'Journal label must contain only letters, digits, dots, underscores, or hyphens, and must start/end with a letter or digit'
            );
        }
    }
}
