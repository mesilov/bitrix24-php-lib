<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Entity;;

use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

class ApplicationInstallation implements ApplicationInstallationInterface
{

    private ?string $comment = null;

    public function __construct(
        private readonly Uuid $id,
        private readonly CarbonImmutable $createdAt,
        private CarbonImmutable $updatedAt,
        // Он должен быть readonly? Я думаю да т.к это связано с установкой и мы не должны менять это свойство.
        private readonly Uuid $bitrix24AccountId,
        // Думаю это тоже readonly т.к связано с установкой
        private readonly Uuid $contactPersonId,
        private Uuid $bitrix24PartnerContactPersonId,
        private ?Uuid $bitrix24PartnerId,
        private string $externalId,
        private ApplicationInstallationStatus $status,
        private ApplicationStatus $applicationStatus,
    ) {

    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
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
    public function getBitrix24AccountId(): Uuid
    {
        return $this->bitrix24AccountId;
    }

    #[\Override]
    public function getContactPersonId(): ?Uuid
    {
        return $this->contactPersonId;
    }

    #[\Override]
    public function changeContactPerson(?Uuid $uuid): void
    {
        // TODO: Implement changeContactPerson() method.
    }

    #[\Override]
    public function getBitrix24PartnerContactPersonId(): ?Uuid
    {
        return $this->bitrix24PartnerContactPersonId;
    }

    #[\Override]
    public function changeBitrix24PartnerContactPerson(?Uuid $uuid): void
    {
        // TODO: Implement changeBitrix24PartnerContactPerson() method.
    }

    #[\Override]
    public function getBitrix24PartnerId(): ?Uuid
    {
        return  $this->bitrix24PartnerId;
    }

    #[\Override]
    public function changeBitrix24Partner(?Uuid $uuid): void
    {
        // TODO: Implement changeBitrix24Partner() method.
    }

    #[\Override]
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    #[\Override]
    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    #[\Override]
    public function getStatus(): ApplicationInstallationStatus
    {
        return $this->status;
    }

    #[\Override]
    public function applicationInstalled(): void
    {
        // TODO: Implement applicationInstalled() method.
    }

    #[\Override]
    public function applicationUninstalled(): void
    {
        // TODO: Implement applicationUninstalled() method.
    }

    #[\Override]
    public function markAsActive(?string $comment): void
    {
        // TODO: Implement markAsActive() method.
    }

    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        // TODO: Implement markAsBlocked() method.
    }

    #[\Override]
    public function getApplicationStatus(): ApplicationStatus
    {
         return $this->applicationStatus;
    }

    #[\Override]
    public function changeApplicationStatus(ApplicationStatus $applicationStatus): void
    {
        // TODO: Implement changeApplicationStatus() method.
    }

    #[\Override]
    public function getPortalLicenseFamily(): PortalLicenseFamily
    {
        // TODO: Implement getPortalLicenseFamily() method.
    }

    #[\Override]
    public function changePortalLicenseFamily(PortalLicenseFamily $portalLicenseFamily): void
    {
        // TODO: Implement changePortalLicenseFamily() method.
    }

    #[\Override]
    public function getPortalUsersCount(): ?int
    {
        // TODO: Implement getPortalUsersCount() method.
    }

    #[\Override]
    public function changePortalUsersCount(int $usersCount): void
    {
        // TODO: Implement changePortalUsersCount() method.
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }
}