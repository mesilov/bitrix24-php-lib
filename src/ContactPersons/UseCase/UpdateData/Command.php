<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\UpdateData;

use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $contactPersonId,
        public ?FullName $fullName = null,
        public ?string $email = null,
        public ?PhoneNumber $mobilePhoneNumber = null,
        public ?string $externalId = null,
        public ?Uuid $bitrix24PartnerId = null
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->fullName instanceof FullName && '' === trim($this->fullName->name)) {
            throw new InvalidArgumentException('Full name cannot be empty.');
        }

        if (null !== $this->email && '' === trim($this->email)) {
            throw new InvalidArgumentException('Email cannot be empty if provided.');
        }

        if (null !== $this->externalId && '' === trim($this->externalId)) {
            throw new InvalidArgumentException('External ID cannot be empty if provided.');
        }
    }
}
