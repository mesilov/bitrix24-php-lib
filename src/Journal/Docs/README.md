# Journal Module

PSR-3 совместимый модуль для ведения технологического журнала приложения.

## Компоненты

### 1. PSR-3 Logger Service

**JournalLogger** - реализация `Psr\Log\LoggerInterface` для записи событий в журнал.

#### Использование через фабрику:

```php
use Bitrix24\Lib\Journal\Services\JournalLoggerFactory;
use Symfony\Component\Uid\Uuid;

// Получаем фабрику из DI контейнера
/** @var JournalLoggerFactory $factory */
$factory = $container->get(JournalLoggerFactory::class);

// Создаем логгер для конкретной установки приложения
$installationId = Uuid::fromString('...');
$memberId = '...';
$logger = $factory->createLogger($memberId, $installationId);

// Используем как обычный PSR-3 логгер
$logger->info('Синхронизация завершена', [
    'label' => 'b24.exchange.realtime',
    'payload' => [
        'action' => 'sync',
        'items' => 150,
        'duration' => '2.5s'
    ],
    'bitrix24UserId' => 123,
    'ipAddress' => '192.168.1.1'
]);

$logger->error('Ошибка обращения к API', [
    'label' => 'b24.api.error',
    'payload' => [
        'method' => 'crm.deal.list',
        'error' => 'QUERY_LIMIT_EXCEEDED'
    ]
]);
```

#### Прямое использование:

```php
use Bitrix24\Lib\Journal\Services\JournalLogger;
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;

$logger = new JournalLogger(
    memberId: $memberId,
    applicationInstallationId: $installationId,
    repository: $repository,
    entityManager: $entityManager
);

// Все PSR-3 методы доступны
$logger->emergency('Критическая ошибка системы');
$logger->alert('Требуется немедленное внимание');
$logger->critical('Критическое состояние');
$logger->error('Ошибка выполнения');
$logger->warning('Предупреждение');
$logger->notice('Важное уведомление');
$logger->info('Информационное сообщение');
$logger->debug('Отладочная информация');
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
    userId: 'user_id',
    context: $context
);
```

### 3. Repositories

#### Doctrine Repository (для продакшена)

```php
use Bitrix24\Lib\Journal\Infrastructure\Doctrine\DoctrineDbalJournalItemRepository;

$repository = new DoctrineDbalJournalItemRepository($entityManager, $paginator);

// Сохранение
$repository->save($journalItem);
$entityManager->flush();

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

// Получение списков для фильтров
$domains = $readRepo->getAvailableDomains();
$labels = $readRepo->getAvailableLabels();
```

## Структура Context

Context записи может содержать:

- **label** (string|null) - метка для группировки событий (например, 'b24.exchange.realtime')
- **payload** (array|null) - произвольные данные в формате JSON
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

class MyTest extends TestCase
{
    private InMemoryJournalItemRepository $repository;
    private JournalLogger $logger;

    protected function setUp(): void
    {
        $this->repository = new InMemoryJournalItemRepository();
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->logger = new JournalLogger(
            '66c9893d5f30e6.45265697',
            Uuid::v7(),
            $this->repository,
            $entityManager
        );
    }

    public function testLogging(): void
    {
        $this->logger->info('Test message');

        $items = $this->repository->findAll();
        $this->assertCount(1, $items);
        $this->assertEquals('Test message', $items[0]->getMessage());
    }
}
```

## Admin Interface

Модуль включает готовый контроллер и Twig-шаблоны для просмотра журнала:

- `/admin/journal` - список с фильтрами (домен, уровень, метка) и пагинацией
- `/admin/journal/{id}` - детальный просмотр с визуализацией JSON payload

См. `src/Journal/Controller/JournalAdminController.php` и `templates/journal/`.

## Database Schema

Таблица `journal_item` с полями:
- `id` (UUID) - PK
- `member_id` (string) - ID портала Bitrix24
- `application_installation_id` (UUID) - FK к установке приложения
- `created_at_utc` (timestamp) - время создания
- `level` (string) - уровень логирования
- `message` (text) - сообщение
- `label`, `payload`, `bitrix24_user_id`, `ip_address` - поля контекста

Индексы:
- `member_id, application_installation_id, level, created_at_utc` (composite)
- `member_id`
- `created_at_utc`
- `level`
