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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\InstallStart;

use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\UnknownScopeCodeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Random\RandomException;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

/**
 * @internal
 */
#[CoversClass(Bitrix24Accounts\UseCase\InstallStart\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Accounts\UseCase\InstallStart\Handler $handler;

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Bitrix24Accounts\UseCase\InstallStart\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws UnknownScopeCodeException
     */
    #[Test]
    public function testInstallNewApplication(): void
    {
        $bitrix24AccountBuilder = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->build();

        $this->handler->handle(
            new Bitrix24Accounts\UseCase\InstallStart\Command(
                $bitrix24AccountBuilder->getBitrix24UserId(),
                $bitrix24AccountBuilder->isBitrix24UserAdmin(),
                $bitrix24AccountBuilder->getMemberId(),
                new Domain($bitrix24AccountBuilder->getDomainUrl()),
                $bitrix24AccountBuilder->getAuthToken(),
                $bitrix24AccountBuilder->getApplicationVersion(),
                $bitrix24AccountBuilder->getApplicationScope()
            )
        );

        $this->assertContains(
            Bitrix24AccountCreatedEvent::class,
            $this->eventDispatcher->getOrphanedEvents(),
            sprintf(
                'not found expected domain event «%s»',
                Bitrix24AccountCreatedEvent::class
            )
        );
    }
}
