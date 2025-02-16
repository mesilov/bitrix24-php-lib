<?php

namespace Bitrix24\Lib;

use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;

class AggregateRoot implements AggregateRootEventsEmitterInterface
{
    protected array $events = [];

    #[\Override]
    public function emitEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
