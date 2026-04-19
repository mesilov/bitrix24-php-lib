<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Create;

use libphonenumber\PhoneNumber;

readonly class Command
{
    public function __construct(
        public string $title,
        public int $bitrix24PartnerNumber,
        public ?string $site = null,
        public ?PhoneNumber $phone = null,
        public ?string $email = null,
        public ?string $openLineId = null,
        public ?string $externalId = null
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === trim($this->title)) {
            throw new \InvalidArgumentException('title must be a non-empty string');
        }

        if ($this->bitrix24PartnerNumber < 0) {
            throw new \InvalidArgumentException('bitrix24PartnerNumber must be non-negative integer');
        }

        if (null !== $this->site && '' === trim($this->site)) {
            throw new \InvalidArgumentException('site must be null or non-empty string');
        }

        if (null !== $this->email) {
            if ('' === trim($this->email)) {
                throw new \InvalidArgumentException('email must be null or non-empty string');
            }

            if (false === filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException(sprintf('email %s is invalid', $this->email));
            }
        }

        if (null !== $this->openLineId && '' === trim($this->openLineId)) {
            throw new \InvalidArgumentException('openLineId must be null or non-empty string');
        }

        if (null !== $this->externalId && '' === trim($this->externalId)) {
            throw new \InvalidArgumentException('externalId must be null or non-empty string');
        }
    }
}
