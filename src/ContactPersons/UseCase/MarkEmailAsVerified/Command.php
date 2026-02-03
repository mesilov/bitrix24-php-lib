<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkEmailAsVerified;

use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $contactPersonId,
        public string $email,
        public ?CarbonImmutable $emailVerifiedAt = null
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $email = trim($this->email);

        if ('' === $email) {
            throw new \InvalidArgumentException('Cannot confirm an empty email.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format.');
        }
    }
}
