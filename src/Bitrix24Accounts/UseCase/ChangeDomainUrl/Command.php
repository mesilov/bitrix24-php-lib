<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use InvalidArgumentException;
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

        $this->validateDomain($oldDomainUrlHost, 'oldDomainUrlHost');
        $this->validateDomain($newDomainUrlHost, 'newDomainUrlHost');
    }

    private function validateDomain(string $domain, string $parameterName): void
    {
        if (empty($domain) || !filter_var($domain, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid value for %s: %s', $parameterName, $domain));
        }
    }
}
