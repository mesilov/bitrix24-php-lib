<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Uninstall;

use Symfony\Component\Uid\Uuid;

final readonly class UninstallationContext
{
    public function __construct(
        public Uuid $installationId,
        public string $memberId,
        public bool $clean,
    ) {}
}
