<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerDeletedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerExternalIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerLogoUrlChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerOpenLineIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerPhoneChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerSiteChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerTitleChangedEvent;
use Symfony\Component\Uid\Uuid;

class ImportStats
{
    public int $created = 0;

    public int $updated = 0;

    public int $softDeleted = 0;

    public int $errors = 0;

    public int $skipped = 0;

    /** @var list<array{partnerNumber: int, error: string}> */
    public array $errorsDetail = [];

    public array $plannedActions = [];

    private array $updatedPartnerIds = [];

    public function onPartnerCreated(Bitrix24PartnerCreatedEvent $event): void
    {
        ++$this->created;
    }

    public function onPartnerTitleChanged(Bitrix24PartnerTitleChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerSiteChanged(Bitrix24PartnerSiteChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerPhoneChanged(Bitrix24PartnerPhoneChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerEmailChanged(Bitrix24PartnerEmailChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerOpenLineIdChanged(Bitrix24PartnerOpenLineIdChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerExternalIdChanged(Bitrix24PartnerExternalIdChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerLogoUrlChanged(Bitrix24PartnerLogoUrlChangedEvent $event): void
    {
        $this->trackUpdated($event->bitrix24PartnerId);
    }

    public function onPartnerDeleted(Bitrix24PartnerDeletedEvent $event): void
    {
        ++$this->softDeleted;
    }

    public function getSkipped(int $totalRows): int
    {
        return max(0, $totalRows - $this->created - $this->updated - $this->errors);
    }

    public function toResult(int $totalRows, bool $dryRun): ImportResult
    {
        return new ImportResult(
            created: $this->created,
            updated: $this->updated,
            skipped: $dryRun ? $this->skipped : $this->getSkipped($totalRows),
            softDeleted: $this->softDeleted,
            errors: $this->errors,
            dryRun: $dryRun,
            plannedActions: $this->plannedActions,
            errorsDetail: $this->errorsDetail,
        );
    }

    private function trackUpdated(Uuid $partnerId): void
    {
        $key = $partnerId->toRfc4122();
        if (!isset($this->updatedPartnerIds[$key])) {
            $this->updatedPartnerIds[$key] = true;
            ++$this->updated;
        }
    }
}
