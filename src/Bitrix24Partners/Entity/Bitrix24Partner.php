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
        private readonly int $bitrix24PartnerNumber,
        private ?string $site = null,
        private ?PhoneNumber $phone = null,
        private ?string $email = null,
        private ?string $openLineId = null,
        private ?string $externalId = null,
        private ?string $logoUrl = null,
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

        if ($oldTitle === $title) {
            return;
        }

        $this->title = $title;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerTitleChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldTitle,
            $title
        );
    }

    #[\Override]
    public function getSite(): ?string
    {
        return $this->site;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function changeLogoUrl(?string $logoUrl): void
    {
        if (null !== $logoUrl) {
            $logoUrl = trim($logoUrl);
            if ('' === $logoUrl) {
                $logoUrl = null;
            }
        }

        $oldLogoUrl = $this->logoUrl;

        if ($oldLogoUrl === $logoUrl) {
            return;
        }

        $this->logoUrl = $logoUrl;
        $this->updatedAt = new CarbonImmutable();

        // TODO заменить на событие Bitrix24PartnerLogoUrlChangedEvent когда добавят в sdk
        $this->events[] = new Bitrix24PartnerSiteChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldLogoUrl,
            $logoUrl
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function setSite(?string $site): void
    {
        if (null !== $site) {
            $site = trim($site);
            if ('' === $site) {
                $site = null;
            }
        }

        $oldSite = $this->site;

        if ($oldSite === $site) {
            return;
        }

        $this->site = $site;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerSiteChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldSite,
            $site
        );
    }

    #[\Override]
    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    #[\Override]
    public function setPhone(?PhoneNumber $phoneNumber): void
    {
        $oldPhone = $this->phone;

        if (null === $oldPhone && null === $phoneNumber) {
            return;
        }

        if (null !== $oldPhone && null !== $phoneNumber && $oldPhone->equals($phoneNumber)) {
            return;
        }

        $this->phone = $phoneNumber;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerPhoneChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldPhone,
            $phoneNumber
        );
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
        if (null !== $email) {
            $email = trim($email);
            if ('' === $email) {
                $email = null;
            }
        }

        $oldEmail = $this->email;

        if ($oldEmail === $email) {
            return;
        }

        $this->email = $email;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerEmailChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldEmail,
            $email
        );
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    #[\Override]
    public function getBitrix24PartnerNumber(): int
    {
        return $this->bitrix24PartnerNumber;
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
        if (null !== $openLineId) {
            $openLineId = trim($openLineId);
            if ('' === $openLineId) {
                $openLineId = null;
            }
        }

        $oldOpenLineId = $this->openLineId;

        if ($oldOpenLineId === $openLineId) {
            return;
        }

        $this->openLineId = $openLineId;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerOpenLineIdChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldOpenLineId,
            $openLineId
        );
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
        if (null !== $externalId) {
            $externalId = trim($externalId);
            if ('' === $externalId) {
                $externalId = null;
            }
        }

        $oldExternalId = $this->externalId;

        if ($oldExternalId === $externalId) {
            return;
        }

        $this->externalId = $externalId;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerExternalIdChangedEvent(
            $this->id,
            new CarbonImmutable(),
            $oldExternalId,
            $externalId
        );
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
            new CarbonImmutable()
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        if (Bitrix24PartnerStatus::active !== $this->status) {
            throw new InvalidArgumentException(
                sprintf(
                    'you can blocked partner only in status «active», now partner in status «%s»',
                    $this->status->name
                )
            );
        }

        $this->status = Bitrix24PartnerStatus::blocked;
        $this->comment = $comment;
        $this->updatedAt = new CarbonImmutable();

        $this->events[] = new Bitrix24PartnerBlockedEvent(
            $this->id,
            new CarbonImmutable()
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
            new CarbonImmutable()
        );
    }

    /**
     * Returns whether this Bitrix24Partner is equal to another.
     *
     * Now we use this method only for testing purposes.
     *
     * @param Bitrix24PartnerInterface $other the Bitrix24Partner to compare
     *
     * @return bool true if the Bitrix24Partner are equal, false otherwise
     */
    public function equals(Bitrix24PartnerInterface $other): bool
    {
        return $this->getTitle() === $other->getTitle()
            && $this->getBitrix24PartnerNumber() === $other->getBitrix24PartnerNumber()
            && $this->getSite() === $other->getSite()
            && (
                (null === $this->getPhone() && null === $other->getPhone())
                || (null !== $this->getPhone() && null !== $other->getPhone() && $this->getPhone()->equals($other->getPhone()))
            )
            && $this->getEmail() === $other->getEmail()
            && $this->getOpenLineId() === $other->getOpenLineId()
            && $this->getExternalId() === $other->getExternalId();
    }
}
