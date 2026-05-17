<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Update;

use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

/**
 * When calling the update use case, you must always pass all partner data,
 * including new changes; otherwise, the data will be overwritten with null.
 */
readonly class Command
{
    public function __construct(
        public Uuid $id,
        public string $title,
        public ?string $site = null,
        public ?PhoneNumber $phone = null,
        public ?string $email = null,
        public ?string $openLineId = null,
        public ?string $externalId = null,
        public ?string $logoUrl = null
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === trim($this->title)) {
            throw new \InvalidArgumentException('title must be non-empty string');
        }

        if (null !== $this->site && '' === trim($this->site)) {
            throw new \InvalidArgumentException('site must be non-empty string');
        }

        if (null !== $this->email) {
            if ('' === trim($this->email)) {
                throw new \InvalidArgumentException('email must be non-empty string');
            }

            if (false === filter_var(trim($this->email), FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException(sprintf('email %s is invalid', $this->email));
            }
        }

        if (null !== $this->openLineId && '' === trim($this->openLineId)) {
            throw new \InvalidArgumentException('openLineId must be non-empty string');
        }

        if (null !== $this->externalId && '' === trim($this->externalId)) {
            throw new \InvalidArgumentException('externalId must be non-empty string');
        }

        if (null !== $this->logoUrl && '' === trim($this->logoUrl)) {
            throw new \InvalidArgumentException('logoUrl must be non-empty string');
        }
    }
}
