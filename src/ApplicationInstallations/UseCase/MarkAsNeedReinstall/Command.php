<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\MarkAsNeedReinstall;

use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $installationId,
        public ?string $comment = null
    ) {}
}
