<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts;

use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Bitrix24Accounts\ReadModel\Fetcher;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Knp\Component\Pager\Event\Subscriber\Paginate\PaginationSubscriber;
use Knp\Component\Pager\Event\Subscriber\Sortable\SortableSubscriber;
use Knp\Component\Pager\Paginator;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\ArgumentAccess\RequestArgumentAccess;

class FetcherTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private PaginatorInterface $paginator;

    private Bitrix24AccountRepositoryInterface $repository;
    private Fetcher $fetcher;

    private Flusher $flusher;

    protected function setUp(): void
    {
        $this->entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new PaginationSubscriber());
        $eventDispatcher->addSubscriber(new SortableSubscriber());
   //     dd(Request::createFromGlobals());
        $requestStack = new RequestStack();
       // Request::createFromGlobals()
        $accessor = new RequestArgumentAccess(new RequestStack());
        $this->paginator = new Paginator($eventDispatcher, $accessor);
        $this->fetcher = new Fetcher($this->entityManager, $this->paginator);
        $this->flusher = new Flusher($this->entityManager);
        $this->repository = new Bitrix24AccountRepository($this->entityManager);
    }

    public function testListReturnsPaginatedResults(): void
    {

        $bitrix24Account = (new Bitrix24AccountBuilder())->build();
        $this->repository->save($bitrix24Account);
        $this->flusher->flush();

        // Параметры для теста
        $page = 1;
        $size = 10;
        // Вызов метода list
        $result = $this->fetcher->list($page, $size);
       // var_dump($result->getItems());
       // var_dump($result->count());
        // Проверка, что результат является экземпляром PaginationInterface
        $this->assertInstanceOf(PaginationInterface::class, $result);

        // Проверка, что данные возвращаются корректно
        $this->assertGreaterThan(0, $result->count()); // Проверяем, что есть хотя бы одна запись
    }

}