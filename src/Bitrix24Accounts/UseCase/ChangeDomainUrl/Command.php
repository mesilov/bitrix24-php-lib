<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

readonly class Command
{
   public Domain $oldDomain;

   public Domain $newDomain;

    public function __construct(
        Domain $oldDomain,
        Domain $newDomain
    ) {
        $this->oldDomain = $oldDomain;
        $this->newDomain = $newDomain;
    }
}
