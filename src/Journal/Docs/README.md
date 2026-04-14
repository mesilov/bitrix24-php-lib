# Journal Module

PSR-3 совместимый модуль для ведения технологического журнала приложения.

## Компоненты

### 1. Journal Logger

**JournalLogger** - сервис для записи объектов `JournalItem` в журнал.

#### Использование:

```php
use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Psr\Log\LogLevel;
use Symfony\Component\Uid\Uuid;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;

$repository = new DoctrineDbalJournalItemRepository($entityManager, $paginator);
$flusher = new Flusher($entityManager, $eventDispatcher);
$logger = new JournalLogger(
    $repository,
    $flusher
);

// Создаем запись журнала напрямую
$item = new JournalItem(
    memberId: '66c9893d5f30e6.45265697',
    applicationInstallationId: Uuid::v7(),
    level: LogLevel::INFO,
    message: 'Синхронизация завершена',
    label: 'b24.exchange.realtime',
    context: new Context(
        ipAddress: $ipAddress, // объект Darsyn\IP\Version\Multi
        payload: ['items' => 150],
        bitrix24UserId: 123
    )
);

// Добавляем в журнал
$logger->add($item);
```

### 2. Entities

**JournalItem** - основная сущность журнала:

```php
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Psr\Log\LogLevel;

// Создание через конструктор с явным указанием уровня
$item = new JournalItem(
    memberId: $memberId,
    applicationInstallationId: $installationId,
    level: LogLevel::INFO,
    message: 'Сообщение',
    label: 'custom.label',
    context: $context
);
```

### 3. Repositories

#### Doctrine Repository (для продакшена)

```php
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;

$repository = new DoctrineDbalJournalItemRepository($entityManager, $paginator);
$flusher = new Flusher($entityManager, $eventDispatcher);

// Сохранение
$repository->save($journalItem);
$flusher->flush();

// Поиск
$item = $repository->findById($uuid);
$items = $repository->findByApplicationInstallationId($memberId, $installationId, LogLevel::ERROR, 50, 0);

// Очистка
$deleted = $repository->deleteOlderThan(new CarbonImmutable('-30 days'));
```

#### In-Memory Repository (для тестов)

```php
use Bitrix24\Lib\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;

$repository = new InMemoryJournalItemRepository();

// Тот же интерфейс, что и Doctrine репозиторий
$repository->save($item);
$items = $repository->findAll();
$repository->clear();
```

### 4. Admin UI (ReadModel)

```php
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;

$readRepo = new DoctrineDbalJournalItemRepository($entityManager, $paginator);

// Получение с фильтрами и пагинацией
$pagination = $readRepo->findWithFilters(
    memberId: '66c9893d5f30e6.45265697',
    domain: new Domain('example.bitrix24.ru'),
    logLevel: LogLevel::ERROR,
    label: 'b24.api.error',
    page: 1,
    limit: 50
);
```

## Структура Context

Context записи может содержать:

- **payload** (array|null) - произвольные данные. В БД хранится json
- **bitrix24UserId** (int|null) - ID пользователя Bitrix24
- **ipAddress** (string|null) - IP адрес (будет сохранен через darsyn/ip library)

## PSR-3 Log Levels

Модуль поддерживает все 8 уровней PSR-3:

1. **emergency** - Система неработоспособна
2. **alert** - Требуется немедленное вмешательство
3. **critical** - Критические условия
4. **error** - Ошибки выполнения
5. **warning** - Предупреждения
6. **notice** - Нормальные, но значимые события
7. **info** - Информационные сообщения
8. **debug** - Детальная отладочная информация

## Testing

Для тестов используйте InMemoryJournalItemRepository:

```php
use Bitrix24\Lib\Journal\Infrastructure\InMemory\InMemoryJournalItemRepository;
use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Journal\Entity\JournalItem;
use Bitrix24\Lib\Journal\Entity\ValueObjects\Context;
use Psr\Log\LogLevel;
use Symfony\Component\Uid\Uuid;

class MyTest extends TestCase
{
    private InMemoryJournalItemRepository $repository;
    private JournalLogger $logger;

    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $flusher = new Flusher($entityManager, $eventDispatcher);

        $this->logger = new JournalLogger(
            $this->repository,
            $flusher
        );
    }

    public function testLogging(): void
    {
        $item = new JournalItem(
            memberId: '66c9893d5f30e6.45265697',
            applicationInstallationId: Uuid::v7(),
            level: LogLevel::INFO,
            message: 'Test message',
            label: 'test.label',
            context: new Context()
        );
        $this->logger->add($item);

        $items = $this->repository->findAll();
        $this->assertCount(1, $items);
        $this->assertEquals('Test message', $items[0]->getMessage());
    }
}
```

## Database Schema

Таблица `b24lib_journal` с полями:
- `id` (UUID) - PK
- `member_id` (string) - ID портала Bitrix24
- `application_installation_id` (UUID) - FK к установке приложения
- `created_at_utc` (timestamp) - время создания
- `level` (string) - уровень логирования
- `message` (text) - сообщение
- `label`,
- `payload`, `bitrix24_user_id`, `ip_address` - поля контекста

### Индексы
- `b24lib_journal_idx_composite (member_id, application_installation_id, level, created_at_utc)`  
  Используется эффективно по левому префиксу:
    - `member_id`
    - `member_id + application_installation_id`
    - `member_id + application_installation_id + level`
- `b24lib_journal_idx_member_id (member_id)`
- `b24lib_journal_idx_created_at (created_at_utc)`
