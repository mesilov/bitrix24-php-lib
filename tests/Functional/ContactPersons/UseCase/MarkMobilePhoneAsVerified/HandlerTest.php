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

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\UseCase\MarkMobilePhoneAsVerified;

use Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified\Handler;
use Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified\Command;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\Lib\Tests\EntityManagerFactory;
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
    public function testConfirmPhoneVerification(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);
        $phoneNumber = $this->createPhoneNumber('+79991234567');

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($phoneNumber)
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->assertFalse($contactPerson->isMobilePhoneVerified());

        $this->handler->handle(new Command($contactPerson->getId(), $phoneNumber));

        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $this->assertTrue($updatedContactPerson->isMobilePhoneVerified());
    }

    #[Test]
    public function testConfirmPhoneVerificationFailsIfContactPersonNotFound(): void
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
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->assertFalse($contactPerson->isMobilePhoneVerified());

        $this->expectException(ContactPersonNotFoundException::class);
        $this->handler->handle(new Command(Uuid::v7(), $this->createPhoneNumber('+79991234567')));
    }

    #[Test]
    public function testConfirmPhoneVerificationFailsOnPhoneMismatch(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        $phoneNumber = $this->createPhoneNumber('+79991234567');
        $expectedDifferentPhone = $this->createPhoneNumber('+79990000000');

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($phoneNumber)
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        // No exception should be thrown; phone mismatch is only logged
        $this->handler->handle(new Command($contactPerson->getId(), $expectedDifferentPhone));

        // Ensure mobile phone is still not verified
        $reloaded = $this->repository->getById($contactPerson->getId());
        $this->assertFalse($reloaded->isMobilePhoneVerified());
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        return $phoneNumberUtil->parse($number, 'RU');
    }
}