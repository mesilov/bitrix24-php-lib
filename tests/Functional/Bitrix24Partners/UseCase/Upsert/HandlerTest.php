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
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
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
        $title = 'New Partner';
        $partnerNumber = 99999;
        $site = 'https://new.com';
        $email = 'new@example.com';
        $logoUrl = 'https://new.com/logo.png';

        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            $title,
            $partnerNumber,
            $site,
            null,
            $email,
            null,
            null,
            $logoUrl
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $this->assertContains(
            Bitrix24PartnerCreatedEvent::class,
            $this->eventDispatcher->getOrphanedEvents()
        );

        $created = $this->repository->findByBitrix24PartnerNumber($partnerNumber);
        $this->assertNotNull($created);
        $this->assertEquals($title, $created->getTitle());
        $this->assertEquals($site, $created->getSite());
        $this->assertEquals($email, $created->getEmail());
        $this->assertEquals($logoUrl, $created->getLogoUrl());
    }

    #[Test]
    public function testSkipPartnerWhenDataIdentical(): void
    {
        $title = 'Hoster.KZ';
        $partnerNumber = 3240;
        $site = 'https://b24.kz';
        $email = 'info@b24.kz';
        $logoUrl = 'https://b24.kz/logo.png';

        $existing = (new Bitrix24PartnerBuilder())
            ->withTitle($title)
            ->withBitrix24PartnerNumber($partnerNumber)
            ->withSite($site)
            ->withEmail($email)
            ->withLogoUrl($logoUrl)
            ->build()
        ;

        $this->repository->save($existing);
        $this->flusher->flush($existing);
        $this->entityManager->clear();

        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            $title,
            $partnerNumber,
            $site,
            null,
            $email,
            null,
            null,
            $logoUrl
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $partner = $this->repository->findByBitrix24PartnerNumber($partnerNumber);
        $this->assertNotNull($partner);
        $this->assertEquals($title, $partner->getTitle());
        $this->assertEquals(
            $existing->getUpdatedAt()->toIso8601String(),
            $partner->getUpdatedAt()->toIso8601String()
        );
    }

    #[Test]
    public function testUpdatePartnerWhenDataDiffers(): void
    {
        $partnerNumber = 3240;
        $title = 'Hoster.KZ';
        $site = 'https://b24.kz';
        $email = 'info@b24.kz';
        $logoUrl = 'https://b24.kz/old-logo.png';

        $newTitle = 'Hoster.KZ NEW';
        $newEmail = 'new@b24.kz';
        $newLogoUrl = 'https://b24.kz/new-logo.png';

        $existing = (new Bitrix24PartnerBuilder())
            ->withTitle($title)
            ->withBitrix24PartnerNumber($partnerNumber)
            ->withSite($site)
            ->withEmail($email)
            ->withLogoUrl($logoUrl)
            ->build()
        ;

        $this->repository->save($existing);
        $this->flusher->flush($existing);
        $this->entityManager->clear();

        $command = new Bitrix24Partners\UseCase\Upsert\Command(
            $newTitle,
            $partnerNumber,
            $site,
            null,
            $newEmail,
            null,
            null,
            $newLogoUrl
        );

        $this->handler->handle($command);

        $this->entityManager->clear();

        $partner = $this->repository->findByBitrix24PartnerNumber($partnerNumber);
        $this->assertNotNull($partner);
        $this->assertEquals($newTitle, $partner->getTitle());
        $this->assertEquals($newEmail, $partner->getEmail());
        $this->assertEquals($newLogoUrl, $partner->getLogoUrl());
        $this->assertEquals($site, $partner->getSite());
    }

    #[Test]
    public function testCreatePartnerWithInvalidPhone(): void
    {
        $this->expectException(InvalidArgumentException::class);
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
