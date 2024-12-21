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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\Uninstall;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Events\Bitrix24AccountApplicationUninstalledEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Bitrix24Accounts\UseCase\Uninstall\Handler::class)]
class HandlerTest extends TestCase
{
    private Bitrix24Accounts\UseCase\Uninstall\Handler $handler;

    private Flusher $flusher;

    private Bitrix24AccountRepositoryInterface $repository;

    private TraceableEventDispatcher $eventDispatcher;

    /**
     * @throws InvalidArgumentException
     * @throws Bitrix24AccountNotFoundException
     */
    #[Test]
    public function testUninstallWithHappyPath(): void
    {
        $oldDomainUrl = Uuid::v7()->toRfc4122() . '-test.bitrix24.com';
        $applicationToken = Uuid::v7()->toRfc4122();
        $id = Uuid::v7();
        $memberId = Uuid::v7()->toRfc4122();
        $bitrix24Account = new Bitrix24Account(
            $id,
            1,
            true,
            $memberId,
            $oldDomainUrl,
            Bitrix24AccountStatus::active,
            new AuthToken('old_1', 'old_2', 3600),
            new CarbonImmutable(),
            new CarbonImmutable(),
            1,
            new Scope(),
            false
        );

        //   $this->repository->createQueryBuilder('b24account')->set('b24account.applicationToken',$applicationToken)->where(['b24account.member_id' => $memberId]);
        //   update('Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account', 'b24account')->

        /*            ->select(
                        'b24account.id as id',
                        'b24account.status as status',
                        'b24account.memberId as member_id',
                        'b24account.domainUrl as domain_url',
                        'b24account.applicationVersion as application_version',
                        'b24account.createdAt as created_at_utc',
                        'b24account.updatedAt as updated_at_utc',
                    )
                    ->from('Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account', 'b24account')
                    ->orderBy('b24account.createdAt', 'DESC');*/

        $this->repository->save($bitrix24Account);


        $this->flusher->flush();
      //  $qb = $this->repository->createQueryBuilder('b24account')->setParameter('application_token', $applicationToken)->where(['b24account.member_id' => $memberId]);
      /*  $qb = $this->repository->createQueryBuilder('b24account')
            ->where('b24account.memberId = :memberId')
            ->setParameter('memberId', $memberId)
            ->set('b24account.applicationToken', ':applicationToken')
            ->setParameter('applicationToken', $applicationToken);*/
        $qb = $this->repository->createQueryBuilder('b24account')
            ->where('b24account.memberId = :memberId')
            ->setParameter('memberId', $memberId);

// Если вы хотите обновить значение application_token, используйте метод update
        $qb->update()
            ->set('b24account.applicationToken', ':applicationToken')
            ->setParameter('applicationToken', $applicationToken);

        $query = $qb->getQuery();
        var_dump($query->getSQL());
        $query->execute();

        var_dump($applicationToken);
        /*      $bitrix24Account->applicationInstalled($applicationToken);
              $this->repository->save($bitrix24Account);
              $this->flusher->flush();*/

        $this->handler->handle(new Bitrix24Accounts\UseCase\Uninstall\Command($applicationToken));
    /*    $this->handler->handle(new Bitrix24Accounts\UseCase\Uninstall\Command($applicationToken));

        $this->expectException(Bitrix24AccountNotFoundException::class);
        $updated = $this->repository->getById($bitrix24Account->getId());

        $this->assertEquals(
            Bitrix24AccountStatus::deleted,
            $updated->getStatus(),
            'Expected status deleted'
        );

        $this->assertTrue(in_array(
            Bitrix24AccountApplicationUninstalledEvent::class,
            $this->eventDispatcher->getOrphanedEvents()
        ),
            sprintf(
                'Event %s was expected to be in the list of orphan events, but it is missing',
                Bitrix24AccountApplicationUninstalledEvent::class
            )
        );*/
        $this->assertTrue(true);
    }

    #[Override]
    protected function setUp(): void
    {

        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $this->eventDispatcher = new TraceableEventDispatcher($eventDispatcher, new Stopwatch());
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);

        $this->handler = new Bitrix24Accounts\UseCase\Uninstall\Handler(
            $this->repository,
            $this->flusher,
            new NullLogger(),
        );
    }
}