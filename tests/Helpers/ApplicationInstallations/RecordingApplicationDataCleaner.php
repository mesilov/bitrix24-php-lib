<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers\ApplicationInstallations;

use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\ApplicationDataCleanerInterface;
use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationContext;

final class RecordingApplicationDataCleaner implements ApplicationDataCleanerInterface
{
    /** @var list<UninstallationContext> */
    public array $contexts = [];

    public ?\Closure $onClean = null;

    public function clean(UninstallationContext $context): void
    {
        $this->contexts[] = $context;
        if ($this->onClean instanceof \Closure) {
            ($this->onClean)($context);
        }
    }
}
