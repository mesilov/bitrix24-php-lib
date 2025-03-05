<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Entity;
;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationBlockedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationUnblockedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationUninstalledEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

class ApplicationInstallation extends AggregateRoot implements ApplicationInstallationInterface
{

    public function __construct(
        private readonly Uuid                 $id,
        private readonly CarbonImmutable      $createdAt,
        private CarbonImmutable               $updatedAt,
        // Он должен быть readonly? Я думаю да т.к это связано с установкой и мы не должны менять это свойство.
        private readonly Uuid                 $bitrix24AccountId,
        // Думаю это тоже readonly т.к связано с установкой, хотя если у нас есть метод changeContactPerson значит мы должны иметь возможность изменить это свойство
        private Uuid                          $contactPersonId,
        private Uuid                          $bitrix24PartnerContactPersonId,
        private ?Uuid                         $bitrix24PartnerId,
        private string                        $externalId,
        private ApplicationInstallationStatus $status,
        private ApplicationStatus             $applicationStatus,
        private PortalLicenseFamily           $portalLicenseFamily,
        private int                           $portalUsersCount,
        private ?string                       $comment = null
    )
    {

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
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Events\ApplicationInstallationContactPersonChangedEvent(
            $this->id,
            $this->updatedAt,
            $this->contactPersonId,
            $uuid
        );

    }

    #[\Override]
    public function getBitrix24PartnerContactPersonId(): ?Uuid
    {
        return $this->bitrix24PartnerContactPersonId;
    }

    #[\Override]
    public function changeBitrix24PartnerContactPerson(?Uuid $uuid): void
    {
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Events\ApplicationInstallationBitrix24PartnerContactPersonChangedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerContactPersonId,
            $uuid
        );
    }

    #[\Override]
    public function getBitrix24PartnerId(): ?Uuid
    {
        return $this->bitrix24PartnerId;
    }

    #[\Override]
    public function changeBitrix24Partner(?Uuid $uuid): void
    {
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Events\ApplicationInstallationBitrix24PartnerChangedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerId,
            $uuid
        );
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
        if (
            ApplicationInstallationStatus::new !== $this->status
            && ApplicationInstallationStatus::blocked !== $this->status
        ) {
            throw new \LogicException(
                sprintf(
                    'installation was interrupted because status must be in new or blocked,but your status is %s',
                    $this->status->value
                )
            );
        }

        $this->status = ApplicationInstallationStatus::active;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new ApplicationInstallationFinishedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerId,
            $this->portalLicenseFamily,
            $this->contactPersonId,
            $this->bitrix24PartnerContactPersonId,
            $this->bitrix24PartnerId
        );

    }

    #[\Override]
    public function applicationUninstalled(): void
    {
        $this->status = ApplicationInstallationStatus::deleted;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new ApplicationInstallationUninstalledEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24AccountId,
            $this->contactPersonId,
            $this->bitrix24PartnerId,
            $this->bitrix24PartnerId,
            $this->externalId
        );

    }

    #[\Override]
    public function markAsActive(?string $comment): void
    {
        if (ApplicationInstallationStatus::blocked !== $this->status) {
            throw new \LogicException(
                sprintf(
                    'you must be in status blocked to complete the installation, now status is «%s»',
                    $this->status->value
                )
            );
        }

        $this->status = ApplicationInstallationStatus::active;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new ApplicationInstallationUnblockedEvent(
            $this->id,
            $this->updatedAt,
            $this->comment
        );
    }

    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        if (
            ApplicationInstallationStatus::new !== $this->status
            && ApplicationInstallationStatus::active !== $this->status
        ) {
            throw new \LogicException(sprintf('You can block application installation only in status new or active,but your status is «%s»',
                $this->status->value
            ));
        }

        $this->status = ApplicationInstallationStatus::blocked;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new ApplicationInstallationBlockedEvent(
            $this->id,
            new CarbonImmutable(),
            $this->comment
        );
    }

    #[\Override]
    public function getApplicationStatus(): ApplicationStatus
    {
        return $this->applicationStatus;
    }

    #[\Override]
    public function changeApplicationStatus(ApplicationStatus $applicationStatus): void
    {
        if ($this->applicationStatus !== $applicationStatus) {
            $this->updatedAt = new CarbonImmutable();

            $this->events[] = new  Events\ApplicationInstallationApplicationStatusChangedEvent(
                $this->id,
                $this->updatedAt,
                $applicationStatus
            );
        }
    }

    #[\Override]
    public function getPortalLicenseFamily(): PortalLicenseFamily
    {
        return $this->portalLicenseFamily;
    }

    #[\Override]
    public function changePortalLicenseFamily(PortalLicenseFamily $portalLicenseFamily): void
    {
        if ($this->portalLicenseFamily !== $portalLicenseFamily) {
            $this->updatedAt = new CarbonImmutable();

            $this->events[] = new  Events\ApplicationInstallationPortalLicenseFamilyChangedEvent(
                $this->id,
                $this->updatedAt,
                $this->portalLicenseFamily,
                $portalLicenseFamily
            );
        }
    }

    #[\Override]
    public function getPortalUsersCount(): ?int
    {
        return $this->portalUsersCount;
    }

    #[\Override]
    public function changePortalUsersCount(int $usersCount): void
    {
        if ($this->portalUsersCount !== $usersCount) {
            $this->updatedAt = new CarbonImmutable();

            $this->events[] = new  Events\ApplicationInstallationPortalUsersCountChangedEvent(
                $this->id,
                $this->updatedAt,
                $this->portalUsersCount,
                $usersCount
            );
        }
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }
}