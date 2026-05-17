<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

readonly class ImportResult
{
    /**
     * @param array<int, array{action: string, partnerNumber: int, title: string, details?: string}> $plannedActions
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public int $softDeleted,
        public int $errors,
        public bool $dryRun,
        public array $plannedActions = [],
    ) {}
}
