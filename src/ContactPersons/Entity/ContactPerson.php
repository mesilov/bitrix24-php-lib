<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonBlockedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonCreatedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonDeletedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailVerifiedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonFullNameChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonMobilePhoneVerifiedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Uid\Uuid;

class ContactPerson extends AggregateRoot implements ContactPersonInterface
{
    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    private ?bool $isEmailVerified = false;

    private ?bool $isMobilePhoneVerified = false;

    public function __construct(
        private readonly Uuid $id,
        private ContactPersonStatus $status,
        private FullName $fullName,
        private ?string $email,
        private ?CarbonImmutable $emailVerifiedAt,
        private ?PhoneNumber $mobilePhoneNumber,
        private ?CarbonImmutable $mobilePhoneVerifiedAt,
        private ?string $comment,
        private ?string $externalId,
        private readonly ?int $bitrix24UserId,
        private ?Uuid $bitrix24PartnerId,
        private readonly ?UserAgentInfo $userAgentInfo,
        private readonly bool $isEmitContactPersonCreatedEvent = false,
    ) {
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
        $this->addContactPersonCreatedEventIfNeeded($this->isEmitContactPersonCreatedEvent);
    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[\Override]
    public function getStatus(): ContactPersonStatus
    {
        return $this->status;
    }

    #[\Override]
    public function markAsActive(?string $comment): void
    {
        if (!in_array($this->status, [ContactPersonStatus::blocked, ContactPersonStatus::deleted], true)) {
            throw new LogicException(sprintf('you must be in status blocked or deleted , now status is «%s»', $this->status->value));
        }

        $this->status = ContactPersonStatus::active;
        $this->updatedAt = new CarbonImmutable();
        if (null !== $comment) {
            $this->comment = $comment;
        }
    }

    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        if (!in_array($this->status, [ContactPersonStatus::active, ContactPersonStatus::deleted], true)) {
            throw new LogicException(sprintf('you must be in status active or deleted, now status is «%s»', $this->status->value));
        }

        $this->status = ContactPersonStatus::blocked;
        $this->updatedAt = new CarbonImmutable();
        if (null !== $comment) {
            $this->comment = $comment;
        }

        $this->events[] = new ContactPersonBlockedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function markAsDeleted(?string $comment): void
    {
        if (!in_array($this->status, [ContactPersonStatus::active, ContactPersonStatus::blocked], true)) {
            throw new LogicException(sprintf('you must be in status active or blocked, now status is «%s»', $this->status->value));
        }

        $this->status = ContactPersonStatus::deleted;
        $this->updatedAt = new CarbonImmutable();
        if (null !== $comment) {
            $this->comment = $comment;
        }

        $this->events[] = new ContactPersonDeletedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function getFullName(): FullName
    {
        return $this->fullName;
    }

    #[\Override]
    public function changeFullName(FullName $fullName): void
    {
        if ('' === trim($fullName->name)) {
            throw new InvalidArgumentException('FullName name cannot be empty.');
        }

        $this->fullName = $fullName;
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new ContactPersonFullNameChangedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    #[\Override]
    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    #[\Override]
    public function getEmail(): ?string
    {
        return $this->email;
    }

    #[\Override]
    public function changeEmail(?string $email): void
    {
        $this->email = $email;

        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new ContactPersonEmailChangedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function markEmailAsVerified(?CarbonImmutable $verifiedAt = null): void
    {
        $this->isEmailVerified = true;
        $this->emailVerifiedAt = $verifiedAt ?? new CarbonImmutable();
        $this->events[] = new ContactPersonEmailVerifiedEvent(
            $this->id,
            $this->emailVerifiedAt,
        );
    }

    #[\Override]
    public function getEmailVerifiedAt(): ?CarbonImmutable
    {
        return $this->emailVerifiedAt;
    }

    #[\Override]
    public function changeMobilePhone(?PhoneNumber $phoneNumber): void
    {
        if ($phoneNumber instanceof PhoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $isValidNumber = $phoneUtil->isValidNumber($phoneNumber);

            if (!$isValidNumber) {
                throw new InvalidArgumentException('Invalid phone number.');
            }

            $numberType = $phoneUtil->getNumberType($phoneNumber);
            if (PhoneNumberType::MOBILE !== $numberType) {
                throw new InvalidArgumentException('Phone number must be mobile.');
            }

            $this->mobilePhoneNumber = $phoneNumber;
        }

        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function getMobilePhone(): ?PhoneNumber
    {
        return $this->mobilePhoneNumber;
    }

    #[\Override]
    public function getMobilePhoneVerifiedAt(): ?CarbonImmutable
    {
        return $this->mobilePhoneVerifiedAt;
    }

    #[\Override]
    public function markMobilePhoneAsVerified(?CarbonImmutable $verifiedAt = null): void
    {
        $this->isMobilePhoneVerified = true;
        $this->mobilePhoneVerifiedAt = $verifiedAt ?? new CarbonImmutable();
        $this->events[] = new ContactPersonMobilePhoneVerifiedEvent(
            $this->id,
            $this->mobilePhoneVerifiedAt,
        );
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    #[\Override]
    public function setExternalId(?string $externalId): void
    {
        if ('' === $externalId) {
            throw new InvalidArgumentException('ExternalId cannot be empty string');
        }

        if ($this->externalId === $externalId) {
            return;
        }

        $this->externalId = $externalId;
        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    #[\Override]
    public function getBitrix24UserId(): ?int
    {
        return $this->bitrix24UserId;
    }

    #[\Override]
    public function getBitrix24PartnerId(): ?Uuid
    {
        return $this->bitrix24PartnerId;
    }

    #[\Override]
    public function setBitrix24PartnerId(?Uuid $uuid): void
    {
        $this->bitrix24PartnerId = $uuid;
        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function isPartner(): bool
    {
        return $this->bitrix24PartnerId instanceof \Symfony\Component\Uid\Uuid;
    }

    #[\Override]
    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    #[\Override]
    public function isMobilePhoneVerified(): bool
    {
        return $this->isMobilePhoneVerified;
    }

    #[\Override]
    public function getUserAgentInfo(): UserAgentInfo
    {
        return $this->userAgentInfo;
    }

    private function addContactPersonCreatedEventIfNeeded(bool $isEmitCreatedEvent): void
    {
        if ($isEmitCreatedEvent) {
            // Create event and add it to events array
            $this->events[] = new ContactPersonCreatedEvent(
                $this->id,
                $this->createdAt
            );
        }
    }
}
