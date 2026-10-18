<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Events;

use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\Event;

/** Best-effort notification after cleanup commits; not the cleanup mechanism. */
final class ApplicationUninstallationCompletedEvent extends Event
{
    public function __construct(
        public readonly Uuid $installationId,
        public readonly bool $clean,
    ) {}
}
