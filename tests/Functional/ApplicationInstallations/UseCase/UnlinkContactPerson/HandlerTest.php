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

namespace Bitrix24\Lib\Tests\Functional\ApplicationInstallations\UseCase\UnlinkContactPerson;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\ApplicationInstallations\UseCase\UnlinkContactPerson\Command;
use Bitrix24\Lib\ApplicationInstallations\UseCase\UnlinkContactPerson\Handler;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationBitrix24PartnerContactPersonUnlinkedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationContactPersonUnlinkedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonDeletedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\CoversClass;
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
    private Handler $handler;

    private Flusher $flusher;

    private ContactPersonRepository $repository;

    private ApplicationInstallationRepository $applicationInstallationRepository;

    private Bitrix24AccountRepository $bitrix24accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->truncateAllTables();
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ContactPersonRepository($entityManager);
        $this->applicationInstallationRepository = new ApplicationInstallationRepository($entityManager);
        $this->bitrix24accountRepository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Handler(
            $this->applicationInstallationRepository,
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    /**
     * @throws InvalidArgumentException|\Random\RandomException
     */
    #[Test]
    public function testUninstallContactPersonSuccess(): void
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
            ->build();

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
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);

        // Создаём контакт и привязываем к установке
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24Account->getBitrix24UserId())
            ->build();

        $this->repository->save($contactPerson);
        $applicationInstallation->linkContactPerson($contactPerson->getId());
        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush();

        var_dump($contactPerson->getId());
        // Запуск use-case
        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                'Deleted by test'
            )
        );

        // Проверки: события отвязки и удаления контакта
        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonDeletedEvent::class, $dispatchedEvents);
        $this->assertContains(ApplicationInstallationContactPersonUnlinkedEvent::class, $dispatchedEvents);

        // Перечитаем установку и проверим, что контакт отвязан
        $foundInstallation = $this->applicationInstallationRepository->getById($applicationInstallation->getId());
        $this->assertNull($foundInstallation->getContactPersonId());

        // Контакт помечен как удалённый и недоступен через getById
        $this->expectException(ContactPersonNotFoundException::class);
        $this->repository->getById($contactPerson->getId());
    }

    #[Test]
    public function testUninstallContactPersonNotFound(): void
    {
        // Подготовка Bitrix24 аккаунта и установки приложения (чтобы getCurrent() вернул установку)
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
            ->build();

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
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush();

        // Ожидаем исключение, т.к. контактного лица с таким ID нет
        $this->expectException(ContactPersonNotFoundException::class);

        $this->handler->handle(
            new Command(
                Uuid::v7(),
                'Deleted by test'
            )
        );
    }

    #[Test]
    public function testUninstallContactPersonWithWrongApplicationInstallationId(): void
    {
        // Создадим контактное лицо, но не будем создавать установку приложения,
        // чтобы репозиторий вернул ApplicationInstallationNotFoundException при getCurrent()
        $externalId = Uuid::v7()->toRfc4122();
        $contactPerson = (new ContactPersonBuilder())
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->expectException(ApplicationInstallationNotFoundException::class);

        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                'Deleted by test'
            )
        );
    }

    /**
     * @throws InvalidArgumentException|\Random\RandomException
     */
    #[Test]
    public function testUninstallPartnerContactPersonSuccess(): void
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
            ->build();

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
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);

        // Создаём контакт и привязываем как партнёрский к установке
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24Account->getBitrix24UserId())
            ->withBitrix24PartnerId(Uuid::v7())
            ->build();

        $this->repository->save($contactPerson);
        $applicationInstallation->linkBitrix24PartnerContactPerson($contactPerson->getId());
        $this->applicationInstallationRepository->save($applicationInstallation);
        $this->flusher->flush();

        // Запуск use-case
        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                'Deleted by test'
            )
        );

        // Проверки: события отвязки и удаления контакта
        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonDeletedEvent::class, $dispatchedEvents);
        $this->assertContains(ApplicationInstallationBitrix24PartnerContactPersonUnlinkedEvent::class, $dispatchedEvents);

        // Перечитаем установку и проверим, что партнёрский контакт отвязан
        $foundInstallation = $this->applicationInstallationRepository->getById($applicationInstallation->getId());
        $this->assertNull($foundInstallation->getBitrix24PartnerContactPersonId());

        // Контакт доступен в репозитории (с пометкой deleted)
        $this->expectException(ContactPersonNotFoundException::class);
        $this->repository->getById($contactPerson->getId());
    }

    #[Test]
    public function testUninstallPartnerContactPersonWithWrongApplicationInstallationId(): void
    {
        // Создадим контактное лицо, но не будем создавать установку приложения,
        // чтобы репозиторий вернул ApplicationInstallationNotFoundException при getCurrent()
        $externalId = Uuid::v7()->toRfc4122();
        $contactPerson = (new ContactPersonBuilder())
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->expectException(ApplicationInstallationNotFoundException::class);

        $this->handler->handle(
            new Command(
                $contactPerson->getId(),
                'Deleted by test'
            )
        );
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        return $phoneNumberUtil->parse($number, 'RU');
    }

    private function truncateAllTables(): void
    {
        $entityManager = EntityManagerFactory::get();
        $connection = $entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        $names = $schemaManager->introspectTableNames();

        if ($names === []) {
            return;
        }

        $quotedTables = [];

        foreach ($names as $name) {
            $tableName = $name->toString();
            $quotedTables[] = $tableName;
        }

        $sql = 'TRUNCATE ' . implode(', ', $quotedTables) . ' RESTART IDENTITY CASCADE';

        $connection->beginTransaction();
        try {
            $connection->executeStatement($sql);
            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

        $entityManager->clear();
    }
}
