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

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\UseCase\MarkEmailAsVerified;

use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\ContactPersons\UseCase\MarkEmailAsVerified\Command;
use Bitrix24\Lib\ContactPersons\UseCase\MarkEmailAsVerified\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Carbon\CarbonImmutable;
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

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ContactPersonRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    public function testConfirmEmailVerificationSuccess(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->assertFalse($contactPerson->isEmailVerified());

        $verifiedAt = new CarbonImmutable('2025-01-01T10:00:00+00:00');
        $this->handler->handle(
            new Command($contactPerson->getId(), 'john.doe@example.com', $verifiedAt)
        );

        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $this->assertTrue($updatedContactPerson->isEmailVerified());
        $this->assertNotNull($updatedContactPerson->getEmailVerifiedAt());
        $this->assertSame($verifiedAt->toISOString(), $updatedContactPerson->getEmailVerifiedAt()?->toISOString());
    }

    #[Test]
    public function testConfirmEmailVerificationFailsIfContactPersonNotFound(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->assertFalse($contactPerson->isEmailVerified());

        $this->expectException(ContactPersonNotFoundException::class);
        $this->handler->handle(new Command(Uuid::v7(), 'john.doe@example.com'));
    }

    #[Test]
    public function testConfirmEmailVerificationFailsIfEmailMismatch(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        // We no longer throw an exception when the email doesn't match — we only log it and make no changes.
        $this->handler->handle(
            new Command($contactPerson->getId(), 'another.email@example.com')
        );

        // Проверяем, что верификация не произошла
        $reloaded = $this->repository->getById($contactPerson->getId());
        $this->assertFalse($reloaded->isEmailVerified());
    }

    #[Test]
    public function testConfirmEmailVerificationFailsIfEntityHasNoEmailButCommandProvidesOne(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        // Не задаём email в сущности (не вызываем withEmail)
        $contactPerson = $contactPersonBuilder
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        // We no longer throw an exception when the email doesn't match — we only log it and make no changes.
        $this->handler->handle(
            new Command($contactPerson->getId(), 'john.doe@example.com')
        );

        // Проверяем, что верификация не произошла
        $reloaded = $this->repository->getById($contactPerson->getId());
        $this->assertFalse($reloaded->isEmailVerified());
    }

    #[Test]
    public function testConfirmEmailVerificationFailsIfInvalidEmailProvided(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->expectException(\InvalidArgumentException::class);
        // An invalid email should fail during validation in the command constructor.
        $this->handler->handle(
            new Command($contactPerson->getId(), 'not-an-email')
        );
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        return $phoneNumberUtil->parse($number, 'RU');
    }
}
