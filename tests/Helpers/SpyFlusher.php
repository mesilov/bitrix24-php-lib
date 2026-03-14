<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;

class SpyFlusher extends Flusher
{
    /** @var list<list<AggregateRootEventsEmitterInterface>> */
    private array $flushCalls = [];

    public function __construct() {}

    #[\Override]
    public function flush(AggregateRootEventsEmitterInterface ...$aggregateRootEventsEmitter): void
    {
        $this->flushCalls[] = array_values($aggregateRootEventsEmitter);
    }

    /**
     * @return list<list<AggregateRootEventsEmitterInterface>>
     */
    public function getFlushCalls(): array
    {
        return $this->flushCalls;
    }
}
