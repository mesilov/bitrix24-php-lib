<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers;

use Psr\Log\AbstractLogger;
use Stringable;

class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    private array $records = [];

    #[\Override]
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function countByLevel(string $level): int
    {
        return count($this->recordsByLevel($level));
    }

    /**
     * @return list<array{level: string, message: string, context: array<mixed>}>
     */
    public function recordsByLevel(string $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['level'] === $level
        ));
    }
}
