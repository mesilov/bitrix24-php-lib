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

2. **markOldInstallations flow (после рефакторинга по ревью):**
```
Console command (ttl=3600 по дефолту)
  → валидация ttl inline (>= 0)
  → repository.findStaleInstallations(status=new, olderThan=NOW()-ttl)   // query side, CQRS read
  → foreach stale:
      → MarkAsNeedReinstall\Handler::handle(Command(installationId, comment))  // command side
        → getById(installationId)         // identity map: без доп. SQL, ре-проверка guard от race с ONAPPINSTALL
        → installation.markAsNeedReinstall(comment)
        → repository.save + flusher.flush → событие ApplicationInstallationMarkedNeedReinstallEvent
      → catch LogicException → skip (race condition: статус изменился параллельно)
  → рендер: помеченные ID + count + пропущенные ID
```

> Рефакторинг по итогам ревью: use case — единоразовая операция над одним агрегатом (без foreach),
> батч-оркестрация вынесена в Console command. Workflow/Config/Result/Collector удалены.

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

#### PR 1: SDK (bitrix24/b24phpsdk) — разбит на issues #576–#580

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

**UseCase — `src/ApplicationInstallations/UseCase/MarkAsNeedReinstall/`** (единоразовая операция над одним агрегатом):

| Файл | Содержание |
|---|---|
| `Command.php` | `installationId: Uuid`, `comment: ?string` |
| `Handler.php` | `handle(Command): void` — `getById` → `markAsNeedReinstall(comment)` → save → flush → лог. Комментарий у `getById` про identity map |

**Удалено (после рефакторинга по ревью):** весь `UseCase/MarkOldInstallations/` — Workflow, Handler, Command, MarkOldInstallationsConfig, MarkOldInstallationsResult, MarkOldInstallationsCollector.

**Console — `src/ApplicationInstallations/Console/MarkOldInstallationsCommand.php`** (батч-оркестратор):

```php
#[AsCommand(name: 'bitrix24:installations:mark-old')]
class MarkOldInstallationsCommand extends Command
{
    public const DEFAULT_TTL = 3600;
    // аргумент: ttl (опциональный, дефолт DEFAULT_TTL), валидация inline
    // конструктор: ApplicationInstallationRepository + MarkAsNeedReinstall\Handler
    // findStaleInstallations(new, NOW()-ttl) → foreach → handler->handle(new Command(id, comment))
    // catch LogicException → skip (race condition с ONAPPINSTALL)
    // рендер: помеченные ID + count, пропущенные ID
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

#### Unit Tests — удалены после рефакторинга

> ConfigTest удалён вместе с MarkOldInstallationsConfig (валидация TTL теперь inline в Console command, консоль не тестируем unit-тестами).
> Entity-тесты (`markAsNeedReinstall` transitions) — идут в SDK, не в этот репо.

#### Functional Tests — ✅ реализованы

**MarkAsNeedReinstall/HandlerTest** (`tests/Functional/ApplicationInstallations/UseCase/MarkAsNeedReinstall/HandlerTest.php`):
1. Pending installation в `new` → handle → статус `needReinstall`, comment сохранён, событие `ApplicationInstallationMarkedNeedReinstallEvent` диспатчнуто
2. Active installation → `LogicException`
3. Неизвестный installationId → `ApplicationInstallationNotFoundException`

**ApplicationInstallationRepositoryTest** — добавлен `testFindStaleInstallationsReturnsOnlyOldEnoughNewOnes`:
- Old `new`-installation (createdAt=NOW()-2h, TTL=3600) → найдена
- Fresh `new`-installation → не найдена
- Old `active`-installation (createdAt=NOW()-2h) → не найдена (статус не тот)
- `backdateCreatedAt` через DQL UPDATE (createdAt readonly в конструкторе)

**Install/HandlerTest** — добавлен тест `testReinstallOverNeedReinstallInstallationDeletesOldEntitiesAndCreatesNewPendingPair`:
- Reinstall поверх `needReinstall` → старая `deleted`, новая пара создана
- `markAsBlocked` пропущен (срабатывает только для `new`), сразу `applicationUninstalled(null)` → `deleted`
- Событие `ApplicationInstallationBlockedEvent` НЕ диспатчнуто

> Удалены после рефакторинга: MarkOldInstallations/ConfigTest, HandlerTest, WorkflowTest (unit + functional).

### Assumptions

- `needReinstall` добавляется только в `ApplicationInstallationStatus`, не в `Bitrix24AccountStatus`
- Мастер-аккаунт при markOld не меняет статус — остаётся в `new`
- `applicationUninstalled(null)` из `needReinstall → deleted` не требует блокировки
- Метод `findStaleInstallations` временно живёт в локальном репозитории; в SDK-интерфейс пойдёт отдельным issue ([b24phpsdk#579](https://github.com/bitrix24/b24phpsdk/issues/579))
- Scheduling (cron/worker) — ответственность потребителя библиотеки
- Константа TTL по умолчанию = 3600 секунд, находится в Console command (`DEFAULT_TTL`)
- При reinstall удаляются ВСЕ аккаунты портала (master + child), а не только master — см. `Install/Handler.php:125-138`
- Use case обрабатывает ровно один агрегат; повторная загрузка через `getById` не даёт доп. SQL (Doctrine identity map) и защищает guard от race с ONAPPINSTALL
- SDK issues: [#576](https://github.com/bitrix24/b24phpsdk/issues/576) (enum), [#577](https://github.com/bitrix24/b24phpsdk/issues/577) (interface method), [#578](https://github.com/bitrix24/b24phpsdk/issues/578) (event), [#579](https://github.com/bitrix24/b24phpsdk/issues/579) (repo interface), [#580](https://github.com/bitrix24/b24phpsdk/issues/580) (reference impl + tests)
