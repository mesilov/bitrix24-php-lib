<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified;

use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $contactPersonId,
        public PhoneNumber $phone,
        public ?CarbonImmutable $phoneVerifiedAt = null,
    ) {
        $this->validate();
    }

    private function validate(): void {}
}
