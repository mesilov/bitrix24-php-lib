<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\InstallFinish;

readonly class Command
{
    public function __construct(
        public string $applicationToken,
        public string $memberId,
        public string $domainUrl,
        public ?int $bitrix24UserId,
    ) {}
}
