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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\InstallFinish;

use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationInstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Bitrix24Accounts\UseCase\InstallFinish\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Accounts\UseCase\InstallFinish\Handler $handler;

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $this->eventDispatcher = new TraceableEventDispatcher($eventDispatcher, new Stopwatch());
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);

        $this->handler = new Bitrix24Accounts\UseCase\InstallFinish\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    #[TestDox('test finish installation with happy path')]
    public function testFinishInstallationWithHappyPath(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withStatus(Bitrix24AccountStatus::new)
            ->build();

        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $applicationToken = Uuid::v7()->toRfc4122();
        $this->handler->handle(
            new Bitrix24Accounts\UseCase\InstallFinish\Command(
                $applicationToken,
                $bitrix24Account->getMemberId(),
                $bitrix24Account->getDomainUrl(),
                $bitrix24Account->getBitrix24UserId()
            )
        );

        $updated = $this->repository->getById($bitrix24Account->getId());
        $this->assertEquals(Bitrix24AccountStatus::active, $updated->getStatus(), 'expected status is active');
        $this->assertTrue(
            $updated->isApplicationTokenValid($applicationToken),
            sprintf(
                'failed application token «%s» validation for bitrix24 account with id «%s»',
                $applicationToken,
                $bitrix24Account->getId()->toString()
            )
        );
        $this->assertTrue(
            in_array(Bitrix24AccountApplicationInstalledEvent::class, $this->eventDispatcher->getOrphanedEvents(), true),
            sprintf(
                'emited event «%s» for bitrix24 account wiht id «%s» not found',
                Bitrix24AccountApplicationInstalledEvent::class,
                $bitrix24Account->getId()->toString()
            )
        );
    }
}
