<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\UseCase\Publish;

use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $id
    ) {}
}
