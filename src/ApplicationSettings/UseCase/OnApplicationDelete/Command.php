<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete;

use Symfony\Component\Uid\Uuid;

/**
 * Command to delete all settings for an application installation.
 *
 * This command is typically triggered when an application is uninstalled.
 * All settings are soft-deleted to maintain history.
 */
readonly class Command
{
    public function __construct(
        public Uuid $applicationInstallationId
    ) {}
}
