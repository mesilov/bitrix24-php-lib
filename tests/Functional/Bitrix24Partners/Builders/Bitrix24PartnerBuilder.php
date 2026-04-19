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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Builders;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

class Bitrix24PartnerBuilder
{
    private string $title;

    private int $bitrix24PartnerNumber;

    private ?string $site = null;

    private ?PhoneNumber $phone = null;

    private ?string $email = null;

    private ?string $openLineId = null;

    private ?string $externalId = null;

    private ?Bitrix24PartnerStatus $status = null;

    public function __construct()
    {
        $this->title = 'Test Partner ' . Uuid::v4()->toRfc4122();
        $this->bitrix24PartnerNumber = random_int(1, 1_000_000);
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withBitrix24PartnerNumber(int $bitrix24PartnerNumber): self
    {
        $this->bitrix24PartnerNumber = $bitrix24PartnerNumber;

        return $this;
    }

    public function withSite(?string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function withPhone(?PhoneNumber $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function withEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withOpenLineId(?string $openLineId): self
    {
        $this->openLineId = $openLineId;

        return $this;
    }

    public function withExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function withStatus(Bitrix24PartnerStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function build(): Bitrix24Partner
    {
        $bitrix24Partner = new Bitrix24Partner(
            $this->title,
            $this->bitrix24PartnerNumber,
            $this->site,
            $this->phone,
            $this->email,
            $this->openLineId,
            $this->externalId
        );

        if ($this->status === Bitrix24PartnerStatus::blocked) {
            $bitrix24Partner->markAsBlocked(null);
        }

        if ($this->status === Bitrix24PartnerStatus::deleted) {
            $bitrix24Partner->markAsDeleted(null);
        }

        return $bitrix24Partner;
    }
}
