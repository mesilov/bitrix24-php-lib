<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountReadModel;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Knp\Component\Pager\ArgumentAccess\ArgumentAccessInterface;
use Knp\Component\Pager\Event\Subscriber\Paginate\PaginationSubscriber;
use Knp\Component\Pager\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Bitrix24AccountReadModel::class)]
class Bitrix24AccountReadModelTest extends TestCase
{
    private Bitrix24AccountReadModel $readModel;

    private Bitrix24AccountRepository $repository;

    private Flusher $flusher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $traceableEventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $traceableEventDispatcher->addSubscriber(new PaginationSubscriber());

        $this->readModel = new Bitrix24AccountReadModel(
            $entityManager,
            new Paginator($traceableEventDispatcher, $this->createStub(ArgumentAccessInterface::class))
        );
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $traceableEventDispatcher);
    }

    #[Test]
    public function testFindAllActiveReturnsOnlyActiveAccounts(): void
    {
        $activeMemberIds = [
            Uuid::v4()->toRfc4122(),
            Uuid::v4()->toRfc4122(),
            Uuid::v4()->toRfc4122(),
        ];

        foreach ($activeMemberIds as $activeMemberId) {
            $activeAccount = new Bitrix24AccountBuilder()
                ->withMemberId($activeMemberId)
                ->withStatus(Bitrix24AccountStatus::new)
                ->withInstalled()
                ->build();

            $this->repository->save($activeAccount);

            $this->flusher->flush($activeAccount);
        }

        $new = new Bitrix24AccountBuilder()->build();
        $blocked = new Bitrix24AccountBuilder()->withStatus(Bitrix24AccountStatus::new)->withInstalled()->build();
        $blocked->markAsBlocked(null);

        $deleted = new Bitrix24AccountBuilder()->withStatus(Bitrix24AccountStatus::new)->withInstalled()->build();
        $deleted->applicationUninstalled(null);

        $this->repository->save($new);
        $this->repository->save($blocked);
        $this->repository->save($deleted);

        $this->flusher->flush($new, $blocked, $deleted);

        $foundActiveAccounts = 0;
        $pagination = $this->readModel->findAllActive('desc', 1, 1000);
        foreach ($pagination as $account) {
            self::assertSame(Bitrix24AccountStatus::active, $account->getStatus());

            if (in_array($account->getMemberId(), $activeMemberIds, true)) {
                $foundActiveAccounts++;
            }
        }

        self::assertSame(3, $foundActiveAccounts, 'findAllActive must return exactly all created active accounts');
    }

    #[Test]
    public function testFindAllActiveThrowsOnInvalidSortDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->readModel->findAllActive('random');
    }
}
