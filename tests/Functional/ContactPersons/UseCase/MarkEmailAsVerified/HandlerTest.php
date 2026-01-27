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

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
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
        $contactPerson = $this->createContactPerson('john.doe@example.com');

        $verifiedAt = new CarbonImmutable('2025-01-01T10:00:00+00:00');
        $this->handler->handle(
            new Command($contactPerson->getId(), 'john.doe@example.com', $verifiedAt)
        );

        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $this->assertTrue($updatedContactPerson->isEmailVerified());
        $this->assertSame($verifiedAt->toISOString(), $updatedContactPerson->getEmailVerifiedAt()?->toISOString());
    }

    #[Test]
    #[DataProvider('invalidMarkEmailVerificationProvider')]
    public function testConfirmEmailVerificationFails(
        bool $useRealContactId,
        string $emailInCommand,
        ?string $expectedExceptionClass = null
    ): void {
        $contactPerson = $this->createContactPerson('john.doe@example.com');
        $contactId = $useRealContactId ? $contactPerson->getId() : Uuid::v7();

        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        $this->handler->handle(new Command($contactId, $emailInCommand));

        if (null === $expectedExceptionClass) {
            // Если исключение не ожидалось (например, при несовпадении email), проверяем, что статус не изменился
            $reloaded = $this->repository->getById($contactPerson->getId());
            $this->assertFalse($reloaded->isEmailVerified());
        }
    }

    public static function invalidMarkEmailVerificationProvider(): array
    {
        return [
            'contact person not found' => [
                'useRealContactId' => false,
                'emailInCommand' => 'john.doe@example.com',
                'expectedExceptionClass' => ContactPersonNotFoundException::class,
            ],
            'email mismatch' => [
                'useRealContactId' => true,
                'emailInCommand' => 'another.email@example.com',
                'expectedExceptionClass' => null,
            ],
            'invalid email format' => [
                'useRealContactId' => true,
                'emailInCommand' => 'not-an-email',
                'expectedExceptionClass' => \InvalidArgumentException::class,
            ],
        ];
    }

    private function createContactPerson(string $email): ContactPerson
    {
        $contactPerson = (new ContactPersonBuilder())
            ->withEmail($email)
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId(Uuid::v7()->toRfc4122())
            ->withBitrix24UserId(random_int(1, 1_000_000))
            ->withBitrix24PartnerId(Uuid::v7())
            ->build()
        ;

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        return $contactPerson;
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        return $phoneNumberUtil->parse($number, 'RU');
    }
}
