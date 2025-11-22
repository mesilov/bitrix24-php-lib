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
use Bitrix24\Lib\Journal\ValueObjects\JournalContext;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Journal item entity
 * Each journal record contains domain business events for technical support staff
 */
class JournalItem extends AggregateRoot implements JournalItemInterface
{
    private readonly Uuid $id;

    private readonly CarbonImmutable $createdAt;

    public function __construct(
        private Uuid $applicationInstallationId,
        private LogLevel $level,
        private string $message,
        private JournalContext $context
    ) {
        if ('' === trim($this->message)) {
            throw new InvalidArgumentException('Journal message cannot be empty');
        }

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
    public function getContext(): JournalContext
    {
        return $this->context;
    }

    /**
     * Create journal item with custom log level
     */
    public static function create(
        Uuid $applicationInstallationId,
        LogLevel $level,
        string $message,
        JournalContext $context
    ): self {
        return new self(
            applicationInstallationId: $applicationInstallationId,
            level: $level,
            message: $message,
            context: $context
        );
    }

    /**
     * PSR-3 compatible factory methods
     */
    public static function emergency(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::emergency, $message, $context);
    }

    public static function alert(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::alert, $message, $context);
    }

    public static function critical(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::critical, $message, $context);
    }

    public static function error(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::error, $message, $context);
    }

    public static function warning(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::warning, $message, $context);
    }

    public static function notice(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::notice, $message, $context);
    }

    public static function info(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::info, $message, $context);
    }

    public static function debug(Uuid $applicationInstallationId, string $message, JournalContext $context): self
    {
        return self::create($applicationInstallationId, LogLevel::debug, $message, $context);
    }
}
