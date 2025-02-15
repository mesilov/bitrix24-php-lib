<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

readonly class Command
{
    public function __construct(public Domain $oldDomain, public Domain $newDomain) {}
}
