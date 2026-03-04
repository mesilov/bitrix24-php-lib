<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ContactPersons\Entity;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Bitrix24\SDK\Tests\Application\Contracts\ContactPersons\Entity\ContactPersonInterfaceTest;
use Carbon\CarbonImmutable;
use Darsyn\IP\Version\Multi as IP;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
class ContactPersonTest extends ContactPersonInterfaceTest
{
    #[\Override]
    protected function createContactPersonImplementation(
        Uuid $uuid,
        CarbonImmutable $createdAt,
        CarbonImmutable $updatedAt,
        ContactPersonStatus $contactPersonStatus,
        int $bitrix24UserId,
        string $name,
        ?string $surname,
        ?string $patronymic,
        ?string $email,
        ?CarbonImmutable $emailVerifiedAt,
        ?string $comment,
        ?PhoneNumber $phoneNumber,
        ?CarbonImmutable $mobilePhoneVerifiedAt,
        ?string $externalId,
        ?Uuid $bitrix24PartnerUuid,
        ?string $userAgent,
        ?string $userAgentReferer,
        ?IP $userAgentIp
    ): ContactPersonInterface {
        return new ContactPerson(
            $uuid,
            $contactPersonStatus,
            $bitrix24UserId,
            new FullName($name, $surname, $patronymic),
            $email,
            $emailVerifiedAt,
            $phoneNumber,
            $mobilePhoneVerifiedAt,
            $comment,
            $externalId,
            $bitrix24PartnerUuid,
            new UserAgentInfo($userAgentIp, $userAgent, $userAgentReferer),
            false,
            $createdAt,
            $updatedAt
        );
    }
}
