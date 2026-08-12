# #110 — Read model для списка активных аккаунтов Bitrix24

## Summary

Нужен read-only способ получить все активные аккаунты Bitrix24
(`status = Bitrix24AccountStatus::active`) с пагинацией и стабильной сортировкой.

Решение согласовано в issue #110: reviewer (camaxtly) явно сказал — интерфейс
не нужен, достаточно маленькой конкретной реализации (read model). Сигнатуру
метода оставили из предложения KarlsonComplete.

## Scope

В рамках задачи:

- новый конкретный класс read model в `src/Bitrix24Accounts/Infrastructure/Doctrine/`
- функциональный тест на него

Вне задачи:

- интерфейс для read model (отвергнуто reviewer)
- CLI-команда в этой библиотеке (ответственность consumer-приложения)
- изменения CHANGELOG.md (по запросу пользователя)
- открытие PR (пользователь открывает самостоятельно)

## Target contract

Метод (точная сигнатура из предложения KarlsonComplete, без интерфейса):

```php
public function findAllActive(string $sort = 'desc', int $page = 1, int $limit = 50): PaginationInterface
```

Поведение:

- `$sort` ∈ `{'asc','desc'}` — направление сортировки по полю `createdAt`.
  При невалидном значении бросаем
  `Bitrix24\SDK\Core\Exceptions\InvalidArgumentException`
  (тот же тип исключения, что используется в bounded context).
- Фильтр: `a.status = Bitrix24AccountStatus::active`
- Возврат: `PaginationInterface<Bitrix24Account>` через Knp paginator
  (`knplabs/knp-paginator-bundle ^6` уже в `composer.json`).
- Образец wiring/стиля: `src/Journal/Infrastructure/Doctrine/DoctrineDbalJournalItemRepository.php`.

## Implementation changes

1. `src/Bitrix24Accounts/Infrastructure/Doctrine/Bitrix24AccountReadModel.php`

   - `final` класс, без интерфейса
   - конструктор: `EntityManagerInterface` + `PaginatorInterface`
   - метод `findAllActive(...)` см. выше
   - `QueryBuilder` по alias `a`: `where a.status = :status` (= `active->name`),
     `orderBy a.createdAt` в направлении `$sort`
   - пагинация: `$this->paginator->paginate($qb, $page, $limit)`
     (без sort-опций Knp — сортировка зафиксирована в QueryBuilder)

2. `tests/Functional/Bitrix24Accounts/Infrastructure/Doctrine/Bitrix24AccountReadModelTest.php`

   - `setUp` по образцу `tests/Functional/Journal/Services/HandlerTest.php`
     (`Paginator` с `TraceableEventDispatcher` + stub `ArgumentAccessInterface`),
     изоляция через transactional `setUp`/`tearDown` (`beginTransaction` / `rollback`)
   - наполнение через `Bitrix24AccountBuilder` + `Bitrix24AccountRepository::save()` + `Flusher::flush()`

## Test cases and scenarios

1. Функциональные:
   - `findAllActive()` возвращает только active-аккаунты (есть new/blocked/deleted — они отсеиваются)
   - метаданные пагинации (`getTotalItemCount`, размер текущей страницы, `limit`)
   - направление сортировки: `desc` (default) = новые первыми, `asc` = старые первыми
     (для детерминизма между созданием аккаунтов делаем `usleep`, чтобы миллисекундные
     `createdAt` различались при precision=3)
   - невалидный `$sort` бросает `InvalidArgumentException`

## Assumptions and defaults

- Базовая ветка: `dev` (локальная)
- Ветка работы: `feature/110-active-accounts-read-model`
- `CHANGELOG.md` не редактируем
- PR открывает пользователь самостоятельно
- Функциональные тесты требуют запущенный Docker + Postgres (`make test-functional`)
- Контроль качества только через `Makefile`: `make lint-all`, `make test-unit`, `make test-functional`
