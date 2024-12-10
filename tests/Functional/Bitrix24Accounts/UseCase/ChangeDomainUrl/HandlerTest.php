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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\ChangeDomainUrl;

use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountDomainUrlChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
/**
 * @internal
 */
#[CoversClass(Bitrix24Accounts\UseCase\ChangeDomainUrl\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Accounts\UseCase\ChangeDomainUrl\Handler $handler;

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->eventDispatcher = new TraceableEventDispatcher($eventDispatcher, new Stopwatch());
        $this->flusher = new Flusher($entityManager,$this->eventDispatcher);
        $this->handler = new Bitrix24Accounts\UseCase\ChangeDomainUrl\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    #[TestDox('Test change domain url with happy path - one account')]
    public function testChangeDomainUrlWithHappyPath(): void
    {
        $oldDomainUrl = Uuid::v7()->toRfc4122() . '-test.bitrix24.com';
        $newDomainUrl = 'new-' . $oldDomainUrl;

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withDomainUrl($oldDomainUrl)
            ->build();
        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $this->handler->handle(
            new Bitrix24Accounts\UseCase\ChangeDomainUrl\Command(
                $oldDomainUrl,
                $newDomainUrl
            )
        );

        $updated = $this->repository->getById($bitrix24Account->getId());
        $this->assertEquals(
            $newDomainUrl,
            $updated->getDomainUrl(),
            sprintf(
                'New domain url %s must be equals domain url %s after update',
                $newDomainUrl,
                $updated->getDomainUrl()
            )
        );

        $this->assertTrue(in_array(
            Bitrix24AccountDomainUrlChangedEvent::class,
            $this->eventDispatcher->getOrphanedEvents(),
        ),
            sprintf(
                'Event %s was expected to be in the list of orphan events, but it is missing',
                Bitrix24AccountDomainUrlChangedEvent::class
            )
        );
    }

    #[Test]
    #[TestDox('Test change domain url with happy path - many accounts')]
    public function testChangeDomainUrlWithHappyPathForManyAccounts(): void
    {
        $oldDomainUrl = Uuid::v7()->toRfc4122() . '-test.bitrix24.com';
        $newDomainUrl = 'new-' . $oldDomainUrl;
        $b24MemberId = Uuid::v7()->toRfc4122();

        $bitrix24AccountA = (new Bitrix24AccountBuilder())
            ->withDomainUrl($oldDomainUrl)
            ->withMemberId($b24MemberId)
            ->build();
        $this->repository->save($bitrix24AccountA);

        $bitrix24AccountB = (new Bitrix24AccountBuilder())
            ->withDomainUrl($oldDomainUrl)
            ->withMemberId($b24MemberId)
            ->build();
        $this->repository->save($bitrix24AccountB);

        $this->flusher->flush();

        $this->handler->handle(
            new Bitrix24Accounts\UseCase\ChangeDomainUrl\Command(
                $oldDomainUrl,
                $newDomainUrl
            )
        );

        $accounts = $this->repository->findByMemberId($b24MemberId);
        foreach ($accounts as $account) {
            $this->assertSame(
                $account->getDomainUrl(),
                $newDomainUrl,
                sprintf(
                    'domain url «%s» mismatch with expected «%s» for account «%s»',
                    $account->getDomainUrl(),
                    $newDomainUrl,
                    $account->getId()->toRfc4122()
                )
            );
        }

        $this->assertTrue(in_array(
            Bitrix24AccountDomainUrlChangedEvent::class,
            $this->eventDispatcher->getOrphanedEvents()
        ),
            sprintf(
                'Event %s was expected to be in the list of orphan events, but it is missing',
                Bitrix24AccountDomainUrlChangedEvent::class
            )
        );
    }
}
