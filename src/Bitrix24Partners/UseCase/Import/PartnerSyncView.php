<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

use Symfony\Component\Uid\Uuid;

readonly class PartnerSyncView
{
    public function __construct(
        public Uuid $id,
        public int $bitrix24PartnerNumber,
        public string $title,
        public ?string $site = null,
        public ?string $email = null,
        public ?string $logoUrl = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'],
            bitrix24PartnerNumber: $row['bitrix24PartnerNumber'],
            title: $row['title'],
            site: $row['site'],
            email: $row['email'],
            logoUrl: $row['logoUrl'],
        );
    }
}
