<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Services;

use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Flusher
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function flush(AggregateRootEventsEmitterInterface ...$aggregateRootEventsEmitter): void
    {
        $this->em->flush();
        foreach ($aggregateRootEventsEmitter as $aggregateRootEventEmitter) {
            $events = $aggregateRootEventEmitter->emitEvents();
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }
    }
}
