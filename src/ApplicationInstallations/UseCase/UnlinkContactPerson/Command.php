<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\UnlinkContactPerson;

use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $contactPersonId,
        public ?string $comment = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        // no-op for now, but keep a place for future checks
    }
}
