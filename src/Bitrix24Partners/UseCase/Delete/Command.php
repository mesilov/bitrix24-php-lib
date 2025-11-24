<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Delete;

use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $id,
        public ?string $comment = null
    ) {}
}
