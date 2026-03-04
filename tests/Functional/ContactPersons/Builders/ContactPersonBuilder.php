<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\Builders;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Uid\Uuid;
use Darsyn\IP\Version\Multi as IP;
use Bitrix24\SDK\Tests\Builders\DemoDataGenerator;

class ContactPersonBuilder
{
    private readonly Uuid $id;

    private ContactPersonStatus $status = ContactPersonStatus::active;

    private FullName $fullName;

    private ?string $email = null;

    private ?PhoneNumber $mobilePhoneNumber = null;

    private ?string $comment = null;

    private ?string $externalId = null;

    private int $bitrix24UserId;

    private ?Uuid $bitrix24PartnerId = null;

    private ?UserAgentInfo $userAgentInfo = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->fullName = DemoDataGenerator::getFullName();
        $this->bitrix24UserId = random_int(1, 1_000_000);
    }

    public function withStatus(ContactPersonStatus $contactPersonStatus): self
    {
        $this->status = $contactPersonStatus;

        return $this;
    }

    public function withFullName(FullName $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withMobilePhoneNumber(PhoneNumber $mobilePhoneNumber): self
    {
        $this->mobilePhoneNumber = $mobilePhoneNumber;

        return $this;
    }

    public function withComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function withExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function withBitrix24UserId(int $bitrix24UserId): self
    {
        $this->bitrix24UserId = $bitrix24UserId;

        return $this;
    }

    public function withBitrix24PartnerId(?Uuid $uuid): self
    {
        $this->bitrix24PartnerId = $uuid;

        return $this;
    }

    public function withUserAgentInfo(UserAgentInfo $userAgentInfo): self
    {
        $this->userAgentInfo = $userAgentInfo;

        return $this;
    }

    public function build(): ContactPerson
    {
        $userAgentInfo = $this->userAgentInfo ?? new UserAgentInfo(
            DemoDataGenerator::getUserAgentIp(),
            DemoDataGenerator::getUserAgent()
        );

        return new ContactPerson(
            $this->id,
            $this->status,
            $this->bitrix24UserId,
            $this->fullName,
            $this->email,
            null,
            $this->mobilePhoneNumber,
            null,
            $this->comment,
            $this->externalId,
            $this->bitrix24PartnerId,
            $userAgentInfo
        );
    }
}