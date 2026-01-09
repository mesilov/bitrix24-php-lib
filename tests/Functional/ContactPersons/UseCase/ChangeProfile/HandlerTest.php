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

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile\Handler;
use Bitrix24\Lib\ContactPersons\UseCase\ChangeProfile\Command;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonFullNameChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonMobilePhoneChangedEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use libphonenumber\PhoneNumberFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumber;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    /**
     * @var \libphonenumber\PhoneNumberUtil
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
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $externalId = Uuid::v7()->toRfc4122();
        $uuidV7 = Uuid::v7();

        // Обновляем контактное лицо через команду
        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                new FullName('Jane Doe'),
                'jane.doe@example.com',
                $this->createPhoneNumber('+79997654321'),
                $externalId,
                $uuidV7,
            )
        );


        // Проверяем, что изменения сохранились
        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $formattedPhone = $phoneNumberUtil->format($updatedContactPerson->getMobilePhone(), PhoneNumberFormat::E164);

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonEmailChangedEvent::class, $dispatchedEvents);
        $this->assertContains(ContactPersonMobilePhoneChangedEvent::class, $dispatchedEvents);
        $this->assertContains(ContactPersonFullNameChangedEvent::class, $dispatchedEvents);
        $this->assertEquals('Jane Doe', $updatedContactPerson->getFullName()->name);
        $this->assertEquals('jane.doe@example.com', $updatedContactPerson->getEmail());
        $this->assertEquals('+79997654321', $formattedPhone);
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        return $phoneNumberUtil->parse($number, 'RU');
    }
}