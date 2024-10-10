<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

readonly class Command
{
    public function __construct(
        /**
         * @var non-empty-string $oldDomainUrlHost
         */
        public string $oldDomainUrlHost,
        /**
         * @var non-empty-string $newDomainUrlHost
         */
        public string $newDomainUrlHost
    )
    {
    }
}