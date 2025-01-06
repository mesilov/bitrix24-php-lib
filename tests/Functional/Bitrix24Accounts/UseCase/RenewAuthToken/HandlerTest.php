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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken\Command;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;

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
        $this->flusher = new Flusher($entityManager,$this->eventDispatcher);
        $this->handler = new Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    #[Test]
    public function testRenewAuthTokenWithoutBitrix24UserId(): void
    {
        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->build();
        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        $newAuthToken = new AuthToken('new_1', 'new_2', 3600);
        $this->handler->handle(
            new Command(
                new RenewedAuthToken(
                    $newAuthToken,
                    $bitrix24Account->getMemberId(),
                    'https://client-endpoint.com',
                    'https://server-endpoint.com',
                    ApplicationStatus::subscription(),
                    $bitrix24Account->getDomainUrl()
                )
            )
        );
        $updated = $this->repository->getById($bitrix24Account->getId());
        $this->assertEquals(
            $newAuthToken->accessToken,
            $updated->getAuthToken()->accessToken,
            sprintf(
                'Expected accessToken %s but got %s',
                $newAuthToken->accessToken,
                $updated->getAuthToken()->accessToken
            )
        );

        $this->assertEquals(
            $newAuthToken->refreshToken,
            $updated->getAuthToken()->refreshToken,
            sprintf(
                'Expected refreshToken %s but got %s',
                $newAuthToken->refreshToken,
                $updated->getAuthToken()->refreshToken
            )
        );
    }
}
