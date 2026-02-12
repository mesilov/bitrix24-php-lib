<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile;

use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $contactPersonId,
        public FullName $fullName,
        public string $email,
        public PhoneNumber $mobilePhoneNumber,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        // Note: empty email is allowed for profile changes.
        // If you pass an empty string (or whitespace), it will be normalized to `null`
        // on the entity level, so the database will store `NULL` instead of an empty string.
        if ('' !== trim($this->email) && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format.');
        }
    }
}
