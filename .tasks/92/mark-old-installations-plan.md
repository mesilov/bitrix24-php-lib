## План по issue #92: фоновая очистка зависших установок — статус needReinstall

### Summary

Issue #92 — фоновая очистка зависших установок в статусе `new`, до которых не дошёл `ONAPPINSTALL`.
Реализуется в два этапа: сначала добавление нового статуса `needReinstall` в SDK, затем использование его в этой библиотеке.

### Scope

**В scope:**
- Новый статус `needReinstall` в SDK (`ApplicationInstallationStatus`)
- Метод `markAsNeedReinstall()` в SDK entity и интерфейсе
- Обновление guard в `applicationUninstalled()` для `needReinstall`
- Новое доменное событие `ApplicationInstallationMarkedNeedReinstallEvent`
- UseCase `MarkOldInstallations` (Workflow → Handler, поиск + перевод в `needReinstall`)
- Repository метод `findStaleInstallations()` в существующем `ApplicationInstallationRepository`
- Console-команда `bitrix24:installations:mark-old`
- Unit + Functional тесты
- Документация и CHANGELOG

**Out of scope:**
- Фильтр по `memberId` — ищем все зависшие установки независимо от портала
- `dry-run` режим — убрали, не нужен
- Проверка реального состояния портала через SDK (`--verify-portal`)
- Автоматическое планирование (cron, worker) — задача потребителя
- Изменение `Bitrix24AccountStatus` — статус `needReinstall` только у `ApplicationInstallation`
- UseCase переустановки — достаточно существующего `Install`, который через `applicationUninstalled` удалит `needReinstall` установку

### Target Contract

1. **Машина состояний ApplicationInstallation после изменений:**
```
new → active (applicationInstalled)
new → blocked (markAsBlocked)
new → needReinstall (markAsNeedReinstall)          ← НОВОЕ
blocked → active (markAsActive)
blocked → deleted (applicationUninstalled)
active → blocked (markAsBlocked)
active → deleted (applicationUninstalled)
needReinstall → deleted (applicationUninstalled)   ← НОВОЕ
```

2. **markOldInstallations flow:**
```
Console command (ttl=3600 по дефолту)
  → Workflow.run(Config)
    → регистрация listener на ApplicationInstallationMarkedNeedReinstallEvent
    → Handler::handle(Command)
      → repository.findStaleInstallations(status=new, olderThan=NOW()-ttl)
      → для каждого: installation.markAsNeedReinstall(comment)
      → repository.save(installation)
      → flusher.flush(installation)
      → событие ApplicationInstallationMarkedNeedReinstallEvent диспатчится через EventDispatcher
    → unregister listener
    → collector.getEvents() → Result(processedInstallations)
  → Console рендерит список переведённых установок
```

3. **Переустановка после markOldInstallations:**
```
Install\Command (повторная установка)
  → Install\Handler::handle()
    → findByBitrix24AccountMemberId находит installation в needReinstall
    → deactivateCurrentInstallation()
      → markAsBlocked пропускается (только для status=new)
      → applicationUninstalled(null)  // needReinstall → deleted (guard обновлён)
      → удаляются ВСЕ аккаунты портала (не только master), см. Install/Handler.php:125-138
    → создание новой пары
```

### Implementation Changes

#### PR 1: SDK (bitrix24/b24phpsdk)

**Файлы SDK:**

| Файл | Изменение |
|---|---|
| `src/Application/Contracts/ApplicationInstallations/Entity/ApplicationInstallationStatus.php` | Добавить `case needReinstall = 'needReinstall'` |
| `src/Application/Contracts/ApplicationInstallations/Entity/ApplicationInstallationInterface.php` | Добавить метод `markAsNeedReinstall(?string $comment): void` + обновить docblock `applicationUninstalled` |
| `src/Application/Contracts/ApplicationInstallations/Events/ApplicationInstallationMarkedNeedReinstallEvent.php` | Новое событие (`applicationInstallationId: Uuid`, `timestamp: CarbonImmutable`, `comment: ?string`) |

> Временно изменения применены прямо в `vendor/bitrix24/b24phpsdk/`, будут формализованы в SDK PR.

#### PR 2: bitrix24-php-lib (этот репозиторий)

**Домен — `src/ApplicationInstallations/Entity/ApplicationInstallation.php`:**

| Изменение | Детали |
|---|---|
| `markAsNeedReinstall(?string $comment)` | Переход `new → needReinstall`, emit `ApplicationInstallationMarkedNeedReinstallEvent` |
| Guard в `applicationUninstalled()` | Добавить `needReinstall` в допустимые статусы (прямой переход `needReinstall → deleted`, без `blocked`) |

**Repository — `src/ApplicationInstallations/Infrastructure/Doctrine/ApplicationInstallationRepository.php`:**

Метод `findStaleInstallations(ApplicationInstallationStatus $status, CarbonImmutable $olderThan): array`:
- Без `memberId` фильтра
- Без JOIN к `Bitrix24Account`
- WHERE `status = :status AND createdAt < :olderThan`
- ORDER BY `createdAt ASC`

**UseCase — `src/ApplicationInstallations/UseCase/MarkOldInstallations/`:**

| Файл | Содержание |
|---|---|
| `Command.php` | `public int $ttlInSeconds` + валидация (>= 0) |
| `Handler.php` | `handle(Command): void` — находит зависшие через `findStaleInstallations`, вызывает `markAsNeedReinstall()`, сохраняет, flush |
| `MarkOldInstallationsConfig.php` | `public int $ttlInSeconds` + валидация |
| `MarkOldInstallationsResult.php` | `public array $processedInstallations` (массив `ApplicationInstallationMarkedNeedReinstallEvent`) |
| `MarkOldInstallationsCollector.php` | Коллектор событий — `add(event)`, `getEvents(): array` |
| `Workflow.php` | Оркестратор: регистрирует listener на EventDispatcher → `handler->handle()` → unregister → `Result(processedInstallations)` |

**Console — `src/ApplicationInstallations/Console/MarkOldInstallationsCommand.php`:**

```php
#[AsCommand(name: 'bitrix24:installations:mark-old')]
class MarkOldInstallationsCommand extends Command
{
    public const DEFAULT_TTL = 3600;
    // аргумент: ttl (опциональный, дефолт DEFAULT_TTL)
    // рендерит список переведённых установок + count
}
```

**Документация — `src/ApplicationInstallations/Docs/application-installations.md`:**
- Добавить секцию «Stale Installation Cleanup» с описанием flow
- Sequence diagram для markOldInstallations
- Sequence diagram для reinstall после needReinstall
- Обновить state machine — новые переходы
- Обновить секцию Follow-Up — отметить что issue #92 реализован

**CHANGELOG.md:**
- Новая запись: feature — mark old installations as needReinstall via background command

### Test Cases

#### Unit Tests — ✅ реализованы

**MarkOldInstallations/ConfigTest** (`tests/Unit/ApplicationInstallations/UseCase/MarkOldInstallations/ConfigTest.php`):
1. TTL = 0, 3600, 1800 — успех
2. TTL = -1 — InvalidArgumentException

> Entity-тесты (`markAsNeedReinstall` transitions) — идут в SDK, не в этот репо.
> Handler/Workflow unit-тесты убраны — это functional concern, моки репы/Handler здесь неуместны.

#### Functional Tests — ✅ реализованы

**MarkOldInstallations/HandlerTest** (`tests/Functional/ApplicationInstallations/UseCase/MarkOldInstallations/HandlerTest.php`):
1. Stale installation (`createdAt = NOW() - 2h`) → TTL=3600 → статус `needReinstall`, событие `ApplicationInstallationMarkedNeedReinstallEvent` диспатчнуто
2. Fresh installation (`createdAt = NOW`) → TTL=3600 → статус остаётся `new`
3. No stale installations → ничего не происходит, событие не диспатчится

**MarkOldInstallations/WorkflowTest** (`tests/Functional/ApplicationInstallations/UseCase/MarkOldInstallations/WorkflowTest.php`):
1. Full flow: stale installation → `Result.processedInstallations` содержит событие с правильным ID
2. No stale installations → `Result` с пустым массивом `processedInstallations`

**Install/HandlerTest** — добавлен тест `testReinstallOverNeedReinstallInstallationDeletesOldEntitiesAndCreatesNewPendingPair`:
- Reinstall поверх `needReinstall` → старая `deleted`, новая пара создана
- `markAsBlocked` пропущен (срабатывает только для `new`), сразу `applicationUninstalled(null)` → `deleted`
- Событие `ApplicationInstallationBlockedEvent` НЕ диспатчнуто

### Assumptions

- `needReinstall` добавляется только в `ApplicationInstallationStatus`, не в `Bitrix24AccountStatus`
- Мастер-аккаунт при markOld не меняет статус — остаётся в `new`
- `applicationUninstalled(null)` из `needReinstall → deleted` не требует блокировки
- Метод `findStaleInstallations` живёт в локальном репозитории этой библиотеки (не в SDK-интерфейсе)
- Scheduling (cron/worker) — ответственность потребителя библиотеки
- Константа TTL по умолчанию = 3600 секунд, находится в Console command (`DEFAULT_TTL`)
- При reinstall удаляются ВСЕ аккаунты портала (master + child), а не только master — см. `Install/Handler.php:125-138`
