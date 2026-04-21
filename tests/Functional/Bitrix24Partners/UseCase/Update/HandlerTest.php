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
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerOpenLineIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerSiteChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerTitleChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
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

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new Bitrix24PartnerRepository($this->entityManager);
        $this->flusher = new Flusher($this->entityManager, $this->eventDispatcher);
        $this->handler = new Bitrix24Partners\UseCase\Update\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    #[DataProvider('updateDataProvider')]
    public function testUpdatePartner(
        string $newTitle,
        string $newSite,
        string $newEmail,
        string $newOpenLineId,
        string $newExternalId,
        array $expectedEvents
    ): void {

        $partner = (new Bitrix24PartnerBuilder())
            ->withTitle('Original Title')
            ->withBitrix24PartnerNumber(123)
            ->withSite('https://original.com')
            ->withEmail('original@example.com')
            ->withOpenLineId('line-orig')
            ->withExternalId('ext-orig')
            ->build();

        $this->repository->save($partner);
        $this->flusher->flush();
        $id = $partner->getId();

        $this->entityManager->clear();

        $command = new Bitrix24Partners\UseCase\Update\Command(
            $id,
            $newTitle,
            $newSite,
            null,
            $newEmail,
            $newOpenLineId,
            $newExternalId
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
        $this->assertEquals($newEmail, $updatedPartner->getEmail());
        $this->assertEquals($newOpenLineId, $updatedPartner->getOpenLineId());
        $this->assertEquals($newExternalId, $updatedPartner->getExternalId());
    }

    #[Test]
    #[DataProvider('invalidUpdateDataProvider')]
    public function testUpdatePartnerWithInvalidData(
        ?string $newTitle,
        ?string $newSite,
        ?string $newEmail,
        ?string $newOpenLineId,
        ?string $newExternalId,
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
            null,
            $newEmail,
            $newOpenLineId,
            $newExternalId
        );
    }

    public static function invalidUpdateDataProvider(): \Generator
    {
        yield 'empty title' => ['', null, null, null, null, 'title must be null or non-empty string'];
        yield 'blank title' => ['  ', null, null, null, null, 'title must be null or non-empty string'];
        yield 'empty site' => [null, '', null, null, null, 'site must be null or non-empty string'];
        yield 'empty email' => [null, null, '', null, null, 'email must be null or non-empty string'];
        yield 'invalid email' => [null, null, 'invalid-email', null, null, 'is invalid'];
        yield 'empty openLineId' => [null, null, null, '', null, 'openLineId must be null or non-empty string'];
        yield 'empty externalId' => [null, null, null, null, '', 'externalId must be null or non-empty string'];
    }

    public static function updateDataProvider(): \Generator
    {
        yield 'update all fields' => [
            'Updated Title',
            'https://updated.com',
            'updated@example.com',
            'line-updated',
            'ext-updated',
            [
                Bitrix24PartnerTitleChangedEvent::class,
                Bitrix24PartnerSiteChangedEvent::class,
                Bitrix24PartnerEmailChangedEvent::class,
                Bitrix24PartnerOpenLineIdChangedEvent::class,
                Bitrix24PartnerExternalIdChangedEvent::class,
            ],
        ];

        yield 'update only title' => [
            'Only Title Updated',
            'https://original.com',
            'original@example.com',
            'line-orig',
            'ext-orig',
            [
                Bitrix24PartnerTitleChangedEvent::class,
            ],
        ];
    }
}
