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

    private readonly CarbonImmutable $createdAt;

    private readonly CarbonImmutable $updatedAt;

    private  Uuid $bitrix24AccountId;

    private readonly ?Uuid $contactPersonId;

    private readonly ?Uuid $bitrix24PartnerContactPersonId;

    private readonly ?Uuid $bitrix24PartnerId;

    private ?string $externalId = null;

    private ApplicationInstallationStatus $status = ApplicationInstallationStatus::active;

    private ApplicationStatus $applicationStatus;

    private PortalLicenseFamily $portalLicenseFamily;

    private readonly ?int $portalUsersCount;

    private ?string $comment = null;


    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = CarbonImmutable::now();
        $this->updatedAt = CarbonImmutable::now();
        $this->bitrix24AccountId = Uuid::v7();
        $this->bitrix24PartnerContactPersonId = Uuid::v7();
        $this->contactPersonId = Uuid::v7();
        $this->bitrix24PartnerId = Uuid::v7();
        $this->portalUsersCount = random_int(1, 1_000_000);
    }

    public function withExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

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

    public function withBitrix24AccountId(Uuid $bitrix24AccountId): self
    {
        $this->bitrix24AccountId = $bitrix24AccountId;

        return $this;
    }

    public function withPortalLicenseFamily(PortalLicenseFamily $portalLicenseFamily): self
    {
        $this->portalLicenseFamily = $portalLicenseFamily;

        return $this;
    }

    public function build(): ApplicationInstallation
    {
        return new ApplicationInstallation(
            $this->id,
            $this->status,
            $this->createdAt,
            $this->updatedAt,
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
    }








}