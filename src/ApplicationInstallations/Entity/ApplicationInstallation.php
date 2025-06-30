<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationBlockedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationContactPersonLinkedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationCreatedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationFinishedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationUnblockedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationUninstalledEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;
use Bitrix24\SDK\Core\Exceptions\LogicException;

/**
 * Установка может происходить по 2 сценриям.
 * 1) UI - Пользователь запускает установку через интерфейс.
 * Bitrix24 отправляет первичный запрос на /install.php с параметрами:
 * AUTH_ID, REFRESH_ID, member_id в теле запроса. Без application_token
 * Система создаёт Bitrix24Account в статусе new.
 * Bitrix24 отправляет событие ONAPPINSTALL на /event-handler.php и передает application_token
 * Вызывается applicationInstalled($applicationToken) с полученным токеном.
 * 2) Без UI - Установка инициируется прямым POST-запросом с полным набором credentials.
 * Сразу создается Bitrix24Account. У установщика и у аккаунта вызывается метод с переданыым токеном:
 * $bitrix24Account->applicationInstalled($applicationToken);
 * $applicationInstallation->applicationInstalled($applicationToken);
 */
class ApplicationInstallation extends AggregateRoot implements ApplicationInstallationInterface
{

    private ?string $applicationToken = null;
    private CarbonImmutable $createdAt;
    private CarbonImmutable $updatedAt;
    private ApplicationInstallationStatus $status;

    public function __construct(
        private readonly Uuid       $id,
        private readonly Uuid       $bitrix24AccountId,
        private ApplicationStatus   $applicationStatus,
        private PortalLicenseFamily $portalLicenseFamily,
        private ?int                $portalUsersCount,
        private ?Uuid               $contactPersonId,
        private ?Uuid               $bitrix24PartnerContactPersonId,
        private ?Uuid               $bitrix24PartnerId,
        private ?string             $externalId,
        private ?string             $comment = null,
        private                     $isEmitApplicationInstallationCreatedEvent = false
    )
    {
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
        $this->status = ApplicationInstallationStatus::new;
        $this->addApplicationCreatedEventIfNeeded($this->isEmitApplicationInstallationCreatedEvent);
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
    public function getBitrix24PartnerContactPersonId(): ?Uuid
    {
        return $this->bitrix24PartnerContactPersonId;
    }

    #[\Override]
    public function getBitrix24PartnerId(): ?Uuid
    {
        return $this->bitrix24PartnerId;
    }

    #[\Override]
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    #[\Override]
    public function setExternalId(?string $externalId): void
    {
        if ('' === $externalId) {
            throw new InvalidArgumentException('ExternalId cannot be empty string');
        }

        $this->externalId = $externalId;
    }

    #[\Override]
    public function getStatus(): ApplicationInstallationStatus
    {
        return $this->status;
    }

    #[\Override]
    public function applicationInstalled(?string $applicationToken = null): void
    {
        if (
            ApplicationInstallationStatus::new !== $this->status
            && ApplicationInstallationStatus::blocked !== $this->status
        ) {
            throw new LogicException(
                sprintf(
                    'installation was interrupted because status must be in new or blocked,but your status is %s',
                    $this->status->value
                )
            );
        }

        if (!empty($applicationToken)) {
            $this->applicationToken = $applicationToken;
        }

        $this->status = ApplicationInstallationStatus::active;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new ApplicationInstallationFinishedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24AccountId,
            $this->portalLicenseFamily,
            $this->contactPersonId,
            $this->bitrix24PartnerContactPersonId,
            $this->bitrix24PartnerId
        );
    }

    #[\Override]
    public function applicationUninstalled(?string $applicationToken = null): void
    {
        if (
            ApplicationInstallationStatus::active !== $this->status
            && ApplicationInstallationStatus::blocked !== $this->status
        ) {
            throw new LogicException(
                sprintf(
                    'installation was interrupted because status must be in active or blocked, but your status is %s',
                    $this->status->value
                )
            );
        }

        // Это проверка для мастер аккаунтов чтобы удостовериться что передан верный токен для деинсталяции.
        if ($applicationToken !== null){
            $this->guardTokenMismatch($applicationToken);
        }

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
            throw new LogicException(
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
            throw new LogicException(sprintf(
                'You can block application installation only in status new or active,but your status is «%s»',
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
            $this->applicationStatus = $applicationStatus;
            $this->updatedAt = new CarbonImmutable();

            $this->events[] = new Events\ApplicationInstallationApplicationStatusChangedEvent(
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
            $this->portalLicenseFamily = $portalLicenseFamily;

            $this->events[] = new Events\ApplicationInstallationPortalLicenseFamilyChangedEvent(
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
        if ($usersCount < 0) {
            throw new \InvalidArgumentException('Users count can not be negative');
        }

        if ($this->portalUsersCount !== $usersCount) {
            $this->portalUsersCount = $usersCount;
            $this->updatedAt = new CarbonImmutable();

            $this->events[] = new Events\ApplicationInstallationPortalUsersCountChangedEvent(
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

    private function addApplicationCreatedEventIfNeeded(bool $isEmitCreatedEvent): void
    {
        if ($isEmitCreatedEvent) {
            // Создание события и добавление его в массив событий
            $this->events[] = new ApplicationInstallationCreatedEvent(
                $this->id,
                $this->createdAt,
                $this->bitrix24AccountId
            );
        }
    }

    #[\Override]
    public function isApplicationTokenValid(string $applicationToken): bool
    {
        return $this->applicationToken === $applicationToken;
    }

    #[\Override]
    public function setApplicationToken(string $applicationToken): void
    {
        if ('' === $applicationToken) {
            throw new InvalidArgumentException('application token cannot be empty');
        }

        $this->updatedAt = new CarbonImmutable();
        $this->applicationToken = $applicationToken;
    }

    #[\Override]
    public function linkContactPerson(Uuid $uuid): void
    {
        $this->updatedAt = new CarbonImmutable();
        $this->contactPersonId = $uuid;

        $this->events[] = new ApplicationInstallationContactPersonLinkedEvent(
            $this->id,
            $this->updatedAt,
            $this->contactPersonId
        );
    }

    #[\Override]
    public function unlinkContactPerson(): void
    {
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Events\ApplicationInstallationContactPersonUnlinkedEvent(
            $this->id,
            $this->updatedAt,
            $this->contactPersonId
        );

        $this->contactPersonId = null;
    }

    #[\Override]
    public function linkBitrix24PartnerContactPerson(Uuid $uuid): void
    {
        $this->updatedAt = new CarbonImmutable();
        $this->bitrix24PartnerContactPersonId = $uuid;

        $this->events[] = new Events\ApplicationInstallationBitrix24PartnerContactPersonLinkedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerContactPersonId
        );
    }

    #[\Override]
    public function unlinkBitrix24PartnerContactPerson(): void
    {
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Events\ApplicationInstallationBitrix24PartnerContactPersonUnlinkedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerContactPersonId
        );

        $this->bitrix24PartnerContactPersonId = null;
    }

    #[\Override]
    public function linkBitrix24Partner(Uuid $uuid): void
    {
        $this->updatedAt = new CarbonImmutable();
        $this->bitrix24PartnerId = $uuid;

        $this->events[] = new Events\ApplicationInstallationBitrix24PartnerLinkedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerId
        );
    }

    #[\Override]
    public function unlinkBitrix24Partner(): void
    {
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Events\ApplicationInstallationBitrix24PartnerUnlinkedEvent(
            $this->id,
            $this->updatedAt,
            $this->bitrix24PartnerId
        );

        $this->bitrix24PartnerId = null;

    }

    private function guardTokenMismatch(string $applicationToken): void
    {
        if (!$this->isApplicationTokenValid($applicationToken)) {
            throw new InvalidArgumentException(
                sprintf(
                    'application token «%s» mismatch with application token «%s» for application installation «%s»',
                    $applicationToken,
                    $this->applicationToken,
                    $this->id->toRfc4122()
                )
            );
        }
    }
}
