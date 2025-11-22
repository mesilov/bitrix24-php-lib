<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerBlockedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerDeletedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerExternalIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerOpenLineIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerPhoneChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerSiteChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerTitleChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerUnblockedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

class Bitrix24Partner extends AggregateRoot implements Bitrix24PartnerInterface
{
    private readonly Uuid $id;
    private readonly CarbonImmutable $createdAt;
    private CarbonImmutable $updatedAt;
    private Bitrix24PartnerStatus $status = Bitrix24PartnerStatus::active;
    private ?string $comment = null;

    public function __construct(
        private string $title,
        private int $bitrix24PartnerId,
        private ?string $site = null,
        private ?PhoneNumber $phone = null,
        private ?string $email = null,
        private ?string $openLineId = null,
        private ?string $externalId = null
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new Bitrix24PartnerCreatedEvent(
            $this->id,
            $this->createdAt
        );
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
    public function getStatus(): Bitrix24PartnerStatus
    {
        return $this->status;
    }

    #[\Override]
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setTitle(string $title): void
    {
        if ('' === trim($title)) {
            throw new InvalidArgumentException('title cannot be empty');
        }

        $oldTitle = $this->title;
        $this->title = $title;
        $this->updatedAt = new CarbonImmutable();

        if ($oldTitle !== $title) {
            $this->events[] = new Bitrix24PartnerTitleChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    #[\Override]
    public function getSite(): ?string
    {
        return $this->site;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setSite(?string $site): void
    {
        if (null !== $site && '' === trim($site)) {
            throw new InvalidArgumentException('site cannot be empty string');
        }

        $oldSite = $this->site;
        $this->site = $site;
        $this->updatedAt = new CarbonImmutable();

        if ($oldSite !== $site) {
            $this->events[] = new Bitrix24PartnerSiteChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    #[\Override]
    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    #[\Override]
    public function setPhone(?PhoneNumber $phone): void
    {
        $oldPhone = $this->phone;
        $this->phone = $phone;
        $this->updatedAt = new CarbonImmutable();

        // Compare phone numbers - both null, or both equal
        $isChanged = !($oldPhone === null && $phone === null)
            && !($oldPhone !== null && $phone !== null && $oldPhone->equals($phone));

        if ($isChanged) {
            $this->events[] = new Bitrix24PartnerPhoneChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    #[\Override]
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setEmail(?string $email): void
    {
        if (null !== $email && '' === trim($email)) {
            throw new InvalidArgumentException('email cannot be empty string');
        }

        $oldEmail = $this->email;
        $this->email = $email;
        $this->updatedAt = new CarbonImmutable();

        if ($oldEmail !== $email) {
            $this->events[] = new Bitrix24PartnerEmailChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    #[\Override]
    public function getBitrix24PartnerId(): int
    {
        return $this->bitrix24PartnerId;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setBitrix24PartnerId(?int $bitrix24PartnerId): void
    {
        if (null === $bitrix24PartnerId || $bitrix24PartnerId < 0) {
            throw new InvalidArgumentException('bitrix24PartnerId cannot be null or negative');
        }

        $oldId = $this->bitrix24PartnerId;
        $this->bitrix24PartnerId = $bitrix24PartnerId;
        $this->updatedAt = new CarbonImmutable();

        if ($oldId !== $bitrix24PartnerId) {
            $this->events[] = new Bitrix24PartnerIdChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    #[\Override]
    public function getOpenLineId(): ?string
    {
        return $this->openLineId;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setOpenLineId(?string $openLineId): void
    {
        if (null !== $openLineId && '' === trim($openLineId)) {
            throw new InvalidArgumentException('openLineId cannot be empty string');
        }

        $oldOpenLineId = $this->openLineId;
        $this->openLineId = $openLineId;
        $this->updatedAt = new CarbonImmutable();

        if ($oldOpenLineId !== $openLineId) {
            $this->events[] = new Bitrix24PartnerOpenLineIdChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    #[\Override]
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setExternalId(?string $externalId): void
    {
        if (null !== $externalId && '' === trim($externalId)) {
            throw new InvalidArgumentException('externalId cannot be empty string');
        }

        $oldExternalId = $this->externalId;
        $this->externalId = $externalId;
        $this->updatedAt = new CarbonImmutable();

        if ($oldExternalId !== $externalId) {
            $this->events[] = new Bitrix24PartnerExternalIdChangedEvent(
                $this->id,
                new CarbonImmutable()
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function markAsActive(?string $comment): void
    {
        if (Bitrix24PartnerStatus::blocked !== $this->status) {
            throw new InvalidArgumentException(
                sprintf(
                    'you can activate partner only in status «blocked», now partner in status «%s»',
                    $this->status->name
                )
            );
        }

        $this->status = Bitrix24PartnerStatus::active;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerUnblockedEvent(
            $this->id,
            new CarbonImmutable(),
            $this->comment
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        if (Bitrix24PartnerStatus::deleted === $this->status) {
            throw new InvalidArgumentException('you cannot block partner in status «deleted»');
        }

        $this->status = Bitrix24PartnerStatus::blocked;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerBlockedEvent(
            $this->id,
            new CarbonImmutable(),
            $this->comment
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function markAsDeleted(?string $comment): void
    {
        if (Bitrix24PartnerStatus::deleted === $this->status) {
            throw new InvalidArgumentException('partner already in status «deleted»');
        }

        $this->status = Bitrix24PartnerStatus::deleted;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerDeletedEvent(
            $this->id,
            new CarbonImmutable(),
            $this->comment
        );
    }

}
