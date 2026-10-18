<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\UseCase\Uninstall\Command;

interface UninstallationStoreInterface
{
    /** Locally authenticate the event and durably remove OAuth credentials. */
    public function begin(Command $command): ?UninstallationContext;

    /** Atomically clean installation-owned data; return false for an already completed operation. */
    public function complete(UninstallationContext $context, ApplicationDataCleanerInterface $cleaner): bool;
}
