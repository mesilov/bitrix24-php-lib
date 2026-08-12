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
        $active = (new Bitrix24AccountBuilder())->withStatus(Bitrix24AccountStatus::new)->withInstalled()->build();
        $new = (new Bitrix24AccountBuilder())->build();
        $blocked = (new Bitrix24AccountBuilder())->withStatus(Bitrix24AccountStatus::new)->withInstalled()->build();
        $blocked->markAsBlocked(null);

        $deleted = (new Bitrix24AccountBuilder())->withStatus(Bitrix24AccountStatus::new)->withInstalled()->build();
        $deleted->applicationUninstalled(null);

        $this->repository->save($active);
        $this->repository->save($new);
        $this->repository->save($blocked);
        $this->repository->save($deleted);

        $this->flusher->flush($active, $new, $blocked, $deleted);

        $found = [];
        $pagination = $this->readModel->findAllActive('desc', 1, 1000);
        foreach ($pagination as $account) {
            self::assertSame(Bitrix24AccountStatus::active, $account->getStatus());
            $found[] = $account->getId()->toRfc4122();
        }

        self::assertContains($active->getId()->toRfc4122(), $found);
        self::assertNotContains($new->getId()->toRfc4122(), $found);
        self::assertNotContains($blocked->getId()->toRfc4122(), $found);
        self::assertNotContains($deleted->getId()->toRfc4122(), $found);
    }

    #[Test]
    public function testFindAllActiveThrowsOnInvalidSortDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->readModel->findAllActive('random');
    }
}
