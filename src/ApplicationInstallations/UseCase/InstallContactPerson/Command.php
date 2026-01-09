<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\UseCase\InstallContactPerson;

use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $applicationInstallationId,
        public FullName $fullName,
        public int $bitrix24UserId,
        public UserAgentInfo $userAgentInfo,
        public ?string $email,
        public ?PhoneNumber $mobilePhoneNumber,
        public ?string $comment,
        public ?string $externalId,
        public ?Uuid $bitrix24PartnerId,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (null !== $this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format.');
        }

        if (null !== $this->externalId && '' === trim($this->externalId)) {
            throw new \InvalidArgumentException('External ID cannot be empty if provided.');
        }

        if ($this->bitrix24UserId <= 0) {
            throw new \InvalidArgumentException('Bitrix24 User ID must be a positive integer.');
        }
    }
}
