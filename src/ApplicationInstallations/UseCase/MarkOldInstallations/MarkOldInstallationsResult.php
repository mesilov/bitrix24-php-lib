<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkOldInstallations;

readonly class MarkOldInstallationsResult
{
    public function __construct(
        public int   $processedCount,
        public bool  $dryRun,
        public array $staleInstallations = []
    )
    {}
}
