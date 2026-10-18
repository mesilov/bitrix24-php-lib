<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Uninstall;

interface ApplicationDataCleanerInterface
{
    /**
     * Disable installation-scoped sessions/jobs for either CLEAN value.
     * Erase application-owned user data only when context.clean is true.
     * Runs in the store transaction: use the same connection, do not commit it.
     * Schedule external work in a transactional outbox; do not call remote APIs.
     * The operation must tolerate a retry after rollback or process failure.
     */
    public function clean(UninstallationContext $context): void;
}
