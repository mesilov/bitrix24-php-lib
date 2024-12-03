<?php

namespace Bitrix24\Lib;

use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Symfony\Contracts\EventDispatcher\Event;

class AggregateRoot implements AggregateRootEventsEmitterInterface
{
    protected array $events = [];

    public function getEvents(): array
    {
        return $this->events;
    }

    #[\Override]
    public function emitEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

}