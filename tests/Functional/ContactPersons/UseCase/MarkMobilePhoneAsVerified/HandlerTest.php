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

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified\Command;
use Bitrix24\Lib\ContactPersons\UseCase\MarkMobilePhoneAsVerified\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
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
        $phoneNumber = $this->createPhoneNumber('+79991234567');
        $contactPerson = $this->createContactPerson($phoneNumber);

        $this->assertFalse($contactPerson->isMobilePhoneVerified());

        $this->handler->handle(new Command($contactPerson->getId(), $phoneNumber));

        $updatedContactPerson = $this->repository->getById($contactPerson->getId());
        $this->assertTrue($updatedContactPerson->isMobilePhoneVerified());
    }

    #[Test]
    #[DataProvider('invalidPhoneVerificationProvider')]
    public function testConfirmPhoneVerificationFails(
        bool $useRealContactId,
        string $phoneNumberInCommand,
        ?string $expectedExceptionClass = null
    ): void {
        $realPhoneNumber = $this->createPhoneNumber('+79991234567');
        $contactPerson = $this->createContactPerson($realPhoneNumber);

        $contactId = $useRealContactId ? $contactPerson->getId() : Uuid::v7();

        if (null !== $expectedExceptionClass) {
            $this->expectException($expectedExceptionClass);
        }

        $phoneNumber = $this->createPhoneNumber($phoneNumberInCommand);
        $this->handler->handle(new Command($contactId, $phoneNumber));

        if (null === $expectedExceptionClass) {
            // Если исключение не ожидалось (например, при несовпадении телефона), проверяем, что статус не изменился
            $reloaded = $this->repository->getById($contactPerson->getId());
            $this->assertFalse($reloaded->isMobilePhoneVerified());
        }
    }

    public static function invalidPhoneVerificationProvider(): array
    {
        return [
            'contact person not found' => [
                'useRealContactId' => false,
                'phoneNumberInCommand' => '+79991234567',
                'expectedExceptionClass' => ContactPersonNotFoundException::class,
            ],
            'phone mismatch' => [
                'useRealContactId' => true,
                'phoneNumberInCommand' => '+79990000000',
                'expectedExceptionClass' => null,
            ],
            'invalid phone format' => [
                'useRealContactId' => true,
                'phoneNumberInCommand' => '123',
                'expectedExceptionClass' => null,
                // Actually Command doesn't validate phone format in this package, it's a PhoneNumber object.
                // In Handler.php there's no guard for phone in MarkMobilePhoneAsVerified, it just compares them.
            ],
        ];
    }

    private function createContactPerson(PhoneNumber $phoneNumber): ContactPerson
    {
        $contactPerson = (new ContactPersonBuilder())
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($phoneNumber)
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
