<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\UseCase\MarkAsActive\MarkAsActive;

use Bitrix24\Lib\Bitrix24Partners;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Builders\Bitrix24PartnerBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerUnblockedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Repository\Bitrix24PartnerRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
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
#[CoversClass(Bitrix24Partners\UseCase\MarkAsActive\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Partners\UseCase\MarkAsActive\Handler $handler;

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
        $this->handler = new Bitrix24Partners\UseCase\MarkAsActive\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    public function testMarkAsActive(): void
    {
        $partner = (new Bitrix24PartnerBuilder())
            ->withTitle('Blocked Partner for activation test')
            ->withStatus(Bitrix24PartnerStatus::blocked)
            ->build();

        $this->repository->save($partner);
        $this->flusher->flush();

        $this->handler->handle(new Bitrix24Partners\UseCase\MarkAsActive\Command($partner->getId(), 'Activation comment'));


        $this->assertContains(
            Bitrix24PartnerUnblockedEvent::class,
            $this->eventDispatcher->getOrphanedEvents(),
            sprintf('not found expected domain event «%s»', Bitrix24PartnerUnblockedEvent::class)
        );

        $this->entityManager->clear();

        $activePartner = $this->repository->getById($partner->getId());

        $this->assertEquals(Bitrix24PartnerStatus::active, $activePartner->getStatus());
    }

    #[Test]
    public function testActivateActivePartnerExpectException(): void
    {
        $partner = (new Bitrix24PartnerBuilder())
            ->withTitle('Already Active Partner for activation test exception')
            ->build();
        $this->repository->save($partner);
        $this->flusher->flush();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('you can activate partner only in status «blocked»');
        $this->handler->handle(
            new Bitrix24Partners\UseCase\MarkAsActive\Command(
                $partner->getId(),
                'Activation attempt'
            )
        );
    }
}
