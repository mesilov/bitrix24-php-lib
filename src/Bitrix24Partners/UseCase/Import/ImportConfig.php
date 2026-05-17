<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

readonly class ImportConfig
{
    public function __construct(
        public string $file,
        public string $syncMode = 'full',
        public bool $dryRun = false,
        public bool $skipErrors = false,
    ) {}
}
