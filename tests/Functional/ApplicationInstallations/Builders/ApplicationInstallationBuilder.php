<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders;

use Bitrix24\Lib\ApplicationInstallations\Entity\ApplicationInstallation;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

class ApplicationInstallationBuilder
{
    private readonly Uuid $id;

    private Uuid $bitrix24AccountId;

    private ?Uuid $contactPersonId;

    private ?Uuid $bitrix24PartnerContactPersonId;

    private ?Uuid $bitrix24PartnerId = null;

    private ?string $externalId = null;

    private ApplicationInstallationStatus $status;

    private ApplicationStatus $applicationStatus;

    private PortalLicenseFamily $portalLicenseFamily;

    private readonly ?int $portalUsersCount;

    private ?string $comment = null;

    private ?string $applicationToken = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->bitrix24AccountId = Uuid::v7();
        $this->bitrix24PartnerContactPersonId = Uuid::v7();
        $this->contactPersonId = Uuid::v7();
        $this->portalUsersCount = random_int(1, 1_000_000);
    }

    public function withExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function withApplicationToken(string $applicationToken): self
    {
        $this->applicationToken = $applicationToken;

        return $this;
    }

    public function withBitrix24PartnerId(?Uuid $uuid): self
    {
        $this->bitrix24PartnerId = $uuid;

        return $this;
    }

    public function withApplicationStatusInstallation(ApplicationInstallationStatus $applicationInstallationStatus): self
    {
        $this->status = $applicationInstallationStatus;

        return $this;
    }

    public function withApplicationStatus(ApplicationStatus $applicationStatus): self
    {
        $this->applicationStatus = $applicationStatus;

        return $this;
    }

    public function withBitrix24AccountId(Uuid $uuid): self
    {
        $this->bitrix24AccountId = $uuid;

        return $this;
    }

    public function withContactPersonId(?Uuid $uuid): self
    {
        $this->contactPersonId = $uuid;

        return $this;
    }

    public function withBitrix24PartnerContactPersonId(?Uuid $uuid): self
    {
        $this->bitrix24PartnerContactPersonId = $uuid;

        return $this;
    }

    public function withPortalLicenseFamily(PortalLicenseFamily $portalLicenseFamily): self
    {
        $this->portalLicenseFamily = $portalLicenseFamily;

        return $this;
    }

    public function build(): ApplicationInstallation
    {
        $applicationInstallation = new ApplicationInstallation(
            $this->id,
            $this->bitrix24AccountId,
            $this->applicationStatus,
            $this->portalLicenseFamily,
            $this->portalUsersCount,
            $this->contactPersonId,
            $this->bitrix24PartnerContactPersonId,
            $this->bitrix24PartnerId,
            $this->externalId,
            $this->comment
        );

        if (!empty($this->status) && $this->status == ApplicationInstallationStatus::active) {
            if ($this->applicationToken !== null && $this->applicationToken !== '') {
                $applicationInstallation->applicationInstalled($this->applicationToken);
            } else {
                $applicationInstallation->applicationInstalled();
            }
        }

        return $applicationInstallation;
    }


}