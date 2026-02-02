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

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\InstallContactPerson;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\InstallContactPerson\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\InstallContactPerson\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationContactPersonLinkedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonCreatedEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

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

    private ApplicationInstallationRepository $applicationInstallationRepository;

    private Bitrix24AccountRepository $bitrix24accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ContactPersonRepository($entityManager);
        $this->applicationInstallationRepository = new ApplicationInstallationRepository($entityManager);
        $this->bitrix24accountRepository = new Bitrix24AccountRepository($entityManager);
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Handler(
            $this->applicationInstallationRepository,
            $this->repository,
            $this->phoneNumberUtil,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    public function testInstallContactPersonSuccess(): void
    {
        // Подготовка Bitrix24 аккаунта и установки приложения
        $applicationToken = Uuid::v7()->toRfc4122();
        $memberId = Uuid::v4()->toRfc4122();
        $externalId = Uuid::v7()->toRfc4122();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->withMaster(true)
            ->withSetToken()
            ->withInstalled()
            ->build()
        ;

        $this->bitrix24accountRepository->save($bitrix24Account);

        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->withContactPersonId(null)
            ->withBitrix24PartnerContactPersonId(null)
            ->withExternalId($externalId)
            ->build()
        ;

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush();

        // Данные контакта
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24Account->getBitrix24UserId())
            ->withBitrix24PartnerId($applicationInstallation->getBitrix24PartnerId())
            ->build()
        ;

        // Запуск use-case
        $this->handler->handle(
            new Command(
                $applicationInstallation->getId(),
                $contactPerson->getFullName(),
                $bitrix24Account->getBitrix24UserId(),
                $contactPerson->getUserAgentInfo(),
                $contactPerson->getEmail(),
                $contactPerson->getMobilePhone(),
                $contactPerson->getComment(),
                $contactPerson->getExternalId(),
                $contactPerson->getBitrix24PartnerId(),
            )
        );

        // Проверки: событие, связь и наличие контакта
        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonCreatedEvent::class, $dispatchedEvents);
        $this->assertContains(ApplicationInstallationContactPersonLinkedEvent::class, $dispatchedEvents);

        $foundInstallation = $this->applicationInstallationRepository->getById($applicationInstallation->getId());
        $this->assertNotNull($foundInstallation->getContactPersonId());

        $uuid = $foundInstallation->getContactPersonId();
        $foundContactPerson = $this->repository->getById($uuid);
        $this->assertEquals($foundContactPerson->getId(), $uuid);
    }

    #[Test]
    public function testInstallContactPersonWithWrongApplicationInstallationId(): void
    {
        // Подготовим входные данные контакта (без реальной установки)
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId(Uuid::v7()->toRfc4122())
            ->build()
        ;

        $uuidV7 = Uuid::v7();

        $this->expectException(ApplicationInstallationNotFoundException::class);

        $this->handler->handle(
            new Command(
                $uuidV7,
                $contactPerson->getFullName(),
                random_int(1, 1_000_000),
                $contactPerson->getUserAgentInfo(),
                $contactPerson->getEmail(),
                $contactPerson->getMobilePhone(),
                $contactPerson->getComment(),
                $contactPerson->getExternalId(),
                $contactPerson->getBitrix24PartnerId(),
            )
        );
    }

    #[Test]
    public function testInstallContactPersonWithInvalidEmail(): void
    {
        // Подготовим входные данные контакта
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('invalid-email')
            ->build()
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format.');

        new Command(
            Uuid::v7(),
            $contactPerson->getFullName(),
            1,
            $contactPerson->getUserAgentInfo(),
            $contactPerson->getEmail(),
            $contactPerson->getMobilePhone(),
            $contactPerson->getComment(),
            $contactPerson->getExternalId(),
            $contactPerson->getBitrix24PartnerId(),
        );
    }

    #[Test]
    #[DataProvider('invalidPhoneProvider')]
    public function testInstallContactPersonWithInvalidPhone(string $phoneNumber, string $region): void
    {
        // Подготовка Bitrix24 аккаунта и установки приложения
        $applicationToken = Uuid::v7()->toRfc4122();
        $memberId = Uuid::v7()->toRfc4122();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->build()
        ;
        $this->bitrix24accountRepository->save($bitrix24Account);

        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationToken($applicationToken)
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->build()
        ;
        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush();

        $invalidPhoneNumber = $this->phoneNumberUtil->parse($phoneNumber, $region);

        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($invalidPhoneNumber)
            ->build()
        ;

        $this->handler->handle(
            new Command(
                $applicationInstallation->getId(),
                $contactPerson->getFullName(),
                $bitrix24Account->getBitrix24UserId(),
                $contactPerson->getUserAgentInfo(),
                $contactPerson->getEmail(),
                $contactPerson->getMobilePhone(),
                $contactPerson->getComment(),
                $contactPerson->getExternalId(),
                $contactPerson->getBitrix24PartnerId(),
            )
        );

        // Проверяем, что контакт не был создан
        $foundInstallation = $this->applicationInstallationRepository->getById($applicationInstallation->getId());
        $this->assertNull($foundInstallation->getBitrix24PartnerId());
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'invalid format' => ['123', 'RU'],
            'not mobile' => ['+74951234567', 'RU'], // Moscow landline
        ];
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        return $phoneNumberUtil->parse($number, 'RU');
    }
}
