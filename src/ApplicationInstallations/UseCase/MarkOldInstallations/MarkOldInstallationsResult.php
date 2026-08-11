<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationMarkedNeedReinstallEvent;

readonly class MarkOldInstallationsResult
{
    /**
     * @param ApplicationInstallationMarkedNeedReinstallEvent[] $processedInstallations
     */
    public function __construct(
        public array $processedInstallations = []
    ) {}
}
