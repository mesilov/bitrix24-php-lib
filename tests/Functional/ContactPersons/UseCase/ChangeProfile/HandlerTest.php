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

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\UseCase\ChangeProfile;

use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile\Command;
use Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonFullNameChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonMobilePhoneChangedEvent;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    /**
     * @var PhoneNumberUtil
     */
    public $phoneNumberUtil;

    private Handler $handler;

    private Flusher $flusher;

    private ContactPersonRepository $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ContactPersonRepository($entityManager);
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Handler(
            $this->repository,
            $this->phoneNumberUtil,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    public function testUpdateExistingContactPerson(): void
    {
        // Создаем контактное лицо через билдера
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Initial comment')
            ->withExternalId(Uuid::v7()->toRfc4122())
            ->withBitrix24UserId(random_int(1, 1_000_000))
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        // Обновляем контактное лицо через команду
        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                new FullName('Jane Doe'),
                'jane.doe@example.com',
                $this->createPhoneNumber('+79997654321')
            )
        );

        // Проверяем, что изменения сохранились
        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $formattedPhone = $this->phoneNumberUtil->format($updatedContactPerson->getMobilePhone(), PhoneNumberFormat::E164);

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonEmailChangedEvent::class, $dispatchedEvents);
        $this->assertContains(ContactPersonMobilePhoneChangedEvent::class, $dispatchedEvents);
        $this->assertContains(ContactPersonFullNameChangedEvent::class, $dispatchedEvents);
        $this->assertEquals('Jane Doe', $updatedContactPerson->getFullName()->name);
        $this->assertEquals('jane.doe@example.com', $updatedContactPerson->getEmail());
        $this->assertEquals('+79997654321', $formattedPhone);
    }

    #[Test]
    public function testUpdateWithNonExistentContactPerson(): void
    {
        $this->expectException(ContactPersonNotFoundException::class);

        $this->handler->handle(
            new Command(
                Uuid::v7(),
                new FullName('Jane Doe'),
                'jane.doe@example.com',
                $this->createPhoneNumber('+79997654321')
            )
        );
    }

    #[Test]
    public function testUpdateWithSameData(): void
    {
        // Создаем контактное лицо через билдера
        $email = 'john.doe@example.com';
        $fullName = new FullName('John Doe');
        $phone = '+79991234567';

        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail($email)
            ->withFullName($fullName)
            ->withMobilePhoneNumber($this->createPhoneNumber($phone))
            ->withExternalId(Uuid::v7()->toRfc4122())
            ->withBitrix24UserId(random_int(1, 1_000_000))
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        // Обновляем контактное лицо теми же данными
        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                $fullName,
                $email,
                $this->createPhoneNumber($phone)
            )
        );

        // Проверяем, что события не были отправлены
        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertNotContains(ContactPersonEmailChangedEvent::class, $dispatchedEvents);
        $this->assertNotContains(ContactPersonMobilePhoneChangedEvent::class, $dispatchedEvents);
        $this->assertNotContains(ContactPersonFullNameChangedEvent::class, $dispatchedEvents);

        // Проверяем, что данные не изменились
        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $this->assertEquals($fullName->name, $updatedContactPerson->getFullName()->name);
        $this->assertEquals($email, $updatedContactPerson->getEmail());
        $this->assertEquals($phone, $this->phoneNumberUtil->format($updatedContactPerson->getMobilePhone(), PhoneNumberFormat::E164));
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        return $phoneNumberUtil->parse($number, 'RU');
    }
}
