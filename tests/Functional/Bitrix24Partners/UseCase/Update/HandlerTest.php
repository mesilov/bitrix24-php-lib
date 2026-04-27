<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\UseCase\Update;

use Bitrix24\Lib\Bitrix24Partners;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Builders\Bitrix24PartnerBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerExternalIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerLogoUrlChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerOpenLineIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerPhoneChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerSiteChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerTitleChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
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

/**
 * @internal
 */
#[CoversClass(Bitrix24Partners\UseCase\Update\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Partners\UseCase\Update\Handler $handler;

    private Flusher $flusher;

    private Bitrix24PartnerRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    private EntityManagerInterface $entityManager;

    private PhoneNumberUtil $phoneNumberUtil;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new Bitrix24PartnerRepository($this->entityManager);
        $this->flusher = new Flusher($this->entityManager, $this->eventDispatcher);
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
        $this->handler = new Bitrix24Partners\UseCase\Update\Handler(
            $this->repository,
            $this->flusher,
            $this->phoneNumberUtil,
            new NullLogger()
        );
    }

    #[Test]
    #[DataProvider('updateDataProvider')]
    public function testUpdatePartner(
        string $newTitle,
        ?string $newSite,
        ?PhoneNumber $newPhone,
        ?string $newEmail,
        ?string $newOpenLineId,
        ?string $newExternalId,
        ?string $newLogoUrl,
        array $expectedEvents
    ): void {

        $partner = (new Bitrix24PartnerBuilder())
            ->withTitle('Original Title')
            ->withBitrix24PartnerNumber(123)
            ->withSite('https://original.com')
            ->withPhone(PhoneNumberUtil::getInstance()->parse('+79001112233', 'RU'))
            ->withEmail('original@example.com')
            ->withOpenLineId('line-orig')
            ->withExternalId('ext-orig')
            ->withLogoUrl('https://original.com/logo.png')
            ->build();

        $this->repository->save($partner);
        $this->flusher->flush();
        $id = $partner->getId();

        $this->entityManager->clear();

        $command = new Bitrix24Partners\UseCase\Update\Command(
            $id,
            $newTitle,
            $newSite,
            $newPhone,
            $newEmail,
            $newOpenLineId,
            $newExternalId,
            $newLogoUrl
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $orphanedEvents = $this->eventDispatcher->getOrphanedEvents();
        foreach ($expectedEvents as $expectedEvent) {
            $this->assertContains(
                $expectedEvent,
                $orphanedEvents,
                sprintf('not found expected domain event «%s»', $expectedEvent)
            );
        }

        $updatedPartner = $this->repository->getById($id);
        $this->assertEquals($newTitle, $updatedPartner->getTitle());
        $this->assertEquals($newSite, $updatedPartner->getSite());
        if (null === $newPhone) {
            $this->assertNull($updatedPartner->getPhone());
        } else {
            $this->assertTrue($newPhone->equals($updatedPartner->getPhone()));
        }
        $this->assertEquals($newEmail, $updatedPartner->getEmail());
        $this->assertEquals($newOpenLineId, $updatedPartner->getOpenLineId());
        $this->assertEquals($newExternalId, $updatedPartner->getExternalId());
        $this->assertEquals($newLogoUrl, $updatedPartner->getLogoUrl());
    }

    #[Test]
    #[DataProvider('invalidUpdateDataProvider')]
    public function testUpdatePartnerWithInvalidData(
        ?string $newTitle,
        ?string $newSite,
        ?PhoneNumber $newPhone,
        ?string $newEmail,
        ?string $newOpenLineId,
        ?string $newExternalId,
        ?string $newLogoUrl,
        string $expectedExceptionMessage
    ): void {
        $partner = (new Bitrix24PartnerBuilder())
            ->withTitle('Original Title')
            ->withBitrix24PartnerNumber(123)
            ->build();
        $this->repository->save($partner);
        $this->flusher->flush();
        $id = $partner->getId();

        $this->entityManager->clear();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new Bitrix24Partners\UseCase\Update\Command(
            $id,
            $newTitle,
            $newSite,
            $newPhone,
            $newEmail,
            $newOpenLineId,
            $newExternalId,
            $newLogoUrl
        );
    }

    public static function invalidUpdateDataProvider(): \Generator
    {
        yield 'empty title' => ['', null, null, null, null, null, null, 'title must be non-empty string'];
        yield 'blank title' => ['  ', null, null, null, null, null, null, 'title must be non-empty string'];
        yield 'invalid email' => ['Valid Title', null, null, 'invalid-email', null, null, null, 'is invalid'];
    }

    public static function updateDataProvider(): \Generator
    {
        yield 'update all fields' => [
            'Updated Title',
            'https://updated.com',
            PhoneNumberUtil::getInstance()->parse('+79001112244', 'RU'),
            'updated@example.com',
            'line-updated',
            'ext-updated',
            'https://updated.com/logo.png',
            [
                Bitrix24PartnerTitleChangedEvent::class,
                Bitrix24PartnerSiteChangedEvent::class,
                Bitrix24PartnerPhoneChangedEvent::class,
                Bitrix24PartnerEmailChangedEvent::class,
                Bitrix24PartnerOpenLineIdChangedEvent::class,
                Bitrix24PartnerExternalIdChangedEvent::class,
                Bitrix24PartnerLogoUrlChangedEvent::class,
            ],
        ];

        yield 'update only title' => [
            'Only Title Updated',
            'https://original.com',
            PhoneNumberUtil::getInstance()->parse('+79001112233', 'RU'),
            'original@example.com',
            'line-orig',
            'ext-orig',
            'https://original.com/logo.png',
            [
                Bitrix24PartnerTitleChangedEvent::class,
            ],
        ];
    }
}
