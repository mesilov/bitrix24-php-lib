<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Services;


use Bitrix24\Lib\AggregateRoot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
class Flusher
{
    private $em;
    private $eventDispatcher;
    public function __construct(EntityManagerInterface $em, EventDispatcherInterface $eventDispatcher) {
        $this->em = $em;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function flush(AggregateRoot ...$roots): void
    {
        $this->em->flush();
        foreach ($roots as $root) {
            $events = $root->emitEvents();
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }
    }
}
