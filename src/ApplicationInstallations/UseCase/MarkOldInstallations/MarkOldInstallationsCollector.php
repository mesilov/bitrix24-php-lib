<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationMarkedNeedReinstallEvent;

class MarkOldInstallationsCollector
{
    /** @var ApplicationInstallationMarkedNeedReinstallEvent[] */
    private array $events = [];

    public function add(ApplicationInstallationMarkedNeedReinstallEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return ApplicationInstallationMarkedNeedReinstallEvent[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
