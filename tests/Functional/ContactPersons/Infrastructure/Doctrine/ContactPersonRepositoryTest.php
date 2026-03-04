<?php

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\Infrastructure\Doctrine;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Tests\Functional\FlusherDecorator;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Tests\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterfaceTest;
use Bitrix24\SDK\Tests\Application\Contracts\TestRepositoryFlusherInterface;
use Carbon\CarbonImmutable;
use Darsyn\IP\Version\Multi as IP;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;

class ContactPersonRepositoryTest extends ContactPersonRepositoryInterfaceTest
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
        ?Uuid $bitrix24PartnerId,
        ?string $userAgent,
        ?string $userAgentReferer,
        ?IP $userAgentIp
    ): ContactPersonInterface
    {
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
            $bitrix24PartnerId,
            new UserAgentInfo($userAgentIp, $userAgent, $userAgentReferer),
            false,
            $createdAt,
            $updatedAt
        );
    }

    #[\Override]
    protected function createContactPersonRepositoryImplementation(): ContactPersonRepositoryInterface
    {
        $entityManager = EntityManagerFactory::get();

        return new ContactPersonRepository($entityManager);
    }

    #[\Override]
    protected function createRepositoryFlusherImplementation(): TestRepositoryFlusherInterface
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();

        return new FlusherDecorator(new Flusher($entityManager, $eventDispatcher));
    }

}