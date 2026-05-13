<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\UseCase\Upsert;

use Bitrix24\Lib\Bitrix24Partners;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Builders\Bitrix24PartnerBuilder;
use Bitrix24\Lib\Tests\Functional\FunctionalTestTrait;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @internal
 */
#[CoversClass(Bitrix24Partners\UseCase\Upsert\Handler::class)]
class HandlerTest extends TestCase
{
    use FunctionalTestTrait;

    private Bitrix24Partners\UseCase\Upsert\Handler $handler;

    private Flusher $flusher;

    private Bitrix24PartnerRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->truncateBitrix24Partners();
        $this->entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new Bitrix24PartnerRepository($this->entityManager);
        $this->flusher = new Flusher($this->entityManager, $this->eventDispatcher);
        $this->handler = new Bitrix24Partners\UseCase\Upsert\Handler(
            $this->repository,
            $this->flusher,
            PhoneNumberUtil::getInstance(),
            new NullLogger()
        );
    }

    #[Test]
    public function testCreatePartnerWhenNotExists(): void
    {
        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            'New Partner',
            99999,
            'https://new.com',
            null,
            'new@example.com',
            null,
            null,
            'https://new.com/logo.png'
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $this->assertContains(
            Bitrix24PartnerCreatedEvent::class,
            $this->eventDispatcher->getOrphanedEvents()
        );

        $created = $this->repository->findByBitrix24PartnerNumber(99999);
        $this->assertNotNull($created);
        $this->assertEquals('New Partner', $created->getTitle());
        $this->assertEquals('https://new.com', $created->getSite());
        $this->assertEquals('new@example.com', $created->getEmail());
        $this->assertEquals('https://new.com/logo.png', $created->getLogoUrl());
    }

    #[Test]
    public function testSkipPartnerWhenDataIdentical(): void
    {
        $partnerNumber = 3240;
        $existing = (new Bitrix24PartnerBuilder())
            ->withTitle('Hoster.KZ')
            ->withBitrix24PartnerNumber($partnerNumber)
            ->withSite('https://b24.kz')
            ->withEmail('info@b24.kz')
            ->withLogoUrl('https://b24.kz/logo.png')
            ->build();

        $this->repository->save($existing);
        $this->flusher->flush($existing);
        $this->entityManager->clear();

        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            'Hoster.KZ',
            $partnerNumber,
            'https://b24.kz',
            null,
            'info@b24.kz',
            null,
            null,
            'https://b24.kz/logo.png'
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $partner = $this->repository->findByBitrix24PartnerNumber($partnerNumber);
        $this->assertNotNull($partner);
        $this->assertEquals('Hoster.KZ', $partner->getTitle());
        $this->assertEquals(
            $existing->getUpdatedAt()->toIso8601String(),
            $partner->getUpdatedAt()->toIso8601String()
        );
    }

    #[Test]
    public function testUpdatePartnerWhenDataDiffers(): void
    {
        $partnerNumber = 3240;
        $existing = (new Bitrix24PartnerBuilder())
            ->withTitle('Hoster.KZ')
            ->withBitrix24PartnerNumber($partnerNumber)
            ->withSite('https://b24.kz')
            ->withEmail('info@b24.kz')
            ->withLogoUrl('https://b24.kz/old-logo.png')
            ->build();

        $this->repository->save($existing);
        $this->flusher->flush($existing);
        $this->entityManager->clear();

        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            'Hoster.KZ NEW',
            $partnerNumber,
            'https://b24.kz',
            null,
            'new@b24.kz',
            null,
            null,
            'https://b24.kz/new-logo.png'
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $partner = $this->repository->findByBitrix24PartnerNumber($partnerNumber);
        $this->assertNotNull($partner);
        $this->assertEquals('Hoster.KZ NEW', $partner->getTitle());
        $this->assertEquals('new@b24.kz', $partner->getEmail());
        $this->assertEquals('https://b24.kz/new-logo.png', $partner->getLogoUrl());
        $this->assertEquals('https://b24.kz', $partner->getSite());
    }

    #[Test]
    public function testCreatePartnerWithInvalidPhone(): void
    {
        $this->expectException(\Bitrix24\SDK\Core\Exceptions\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mobile phone number.');

        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            'Bad Phone Partner',
            random_int(1000, 9999),
            null,
            PhoneNumberUtil::getInstance()->parse('+70000000000', 'RU'),
            null,
            null,
            null,
            null
        );

        $this->handler->handle($command);
    }
}
