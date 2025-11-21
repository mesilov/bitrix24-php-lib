<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Update;

use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $id,
        public ?string $title = null,
        public ?string $site = null,
        public ?PhoneNumber $phone = null,
        public ?string $email = null,
        public ?int $bitrix24PartnerId = null,
        public ?string $openLineId = null,
        public ?string $externalId = null
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (null !== $this->title && '' === trim($this->title)) {
            throw new \InvalidArgumentException('title must be null or non-empty string');
        }

        if (null !== $this->site && '' === trim($this->site)) {
            throw new \InvalidArgumentException('site must be null or non-empty string');
        }

        if (null !== $this->email && '' === trim($this->email)) {
            throw new \InvalidArgumentException('email must be null or non-empty string');
        }

        if (null !== $this->bitrix24PartnerId && $this->bitrix24PartnerId < 0) {
            throw new \InvalidArgumentException('bitrix24PartnerId must be null or non-negative integer');
        }

        if (null !== $this->openLineId && '' === trim($this->openLineId)) {
            throw new \InvalidArgumentException('openLineId must be null or non-empty string');
        }

        if (null !== $this->externalId && '' === trim($this->externalId)) {
            throw new \InvalidArgumentException('externalId must be null or non-empty string');
        }
    }
}
