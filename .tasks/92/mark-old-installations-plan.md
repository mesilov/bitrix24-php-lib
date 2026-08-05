## План по issue #92: фоновая очистка зависших установок — статус needReinstall

### Summary

Issue #92 — фоновая очистка зависших установок в статусе `new`, до которых не дошёл `ONAPPINSTALL`.
Реализуется в два этапа: сначала добавление нового статуса `needReinstall` в SDK, затем использование его в этой библиотеке.

### Scope

**В scope:**
- Новый статус `needReinstall` в SDK (`ApplicationInstallationStatus`)
- Метод `markAsNeedReinstall()` в SDK-интерфейсе и entity
- Обновление guard в `applicationUninstalled()` для `needReinstall`
- Новое доменное событие `ApplicationInstallationMarkedNeedReinstallEvent`
- UseCase `MarkOldInstallations` (поиск + перевод в `needReinstall`)
- Repository метод для поиска зависших установок (в локальном репо, потом в SDK)
- Console-команда `bitrix24:installations:mark-old`
- Unit + Functional тесты
- Документация и CHANGELOG

**Out of scope:**
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
  → MarkOldInstallations\Command(ttl)
  → Handler::handle()
    → repository.findStaleInstallations(status=new, olderThan=NOW()-ttl)
    → для каждого: installation.markAsNeedReinstall(comment)
    → repository.save(installation)
    → flusher.flush(installation)
    → событие ApplicationInstallationMarkedNeedReinstallEvent диспатчится
```

3. **Переустановка после markOldInstallations:**
```
Install\Command (повторная установка)
  → Install\Handler::handle()
    → findByBitrix24AccountMemberId находит installation в needReinstall
    → deactivateCurrentInstallation()
      → applicationUninstalled(null)  // needReinstall → deleted (guard обновлён)
      → bitrix24Account.applicationUninstalled(null)  // new → deleted (уже работает)
    → создание новой пары
```

### Implementation Changes

#### PR 1: SDK (bitrix24/b24phpsdk)

**Файлы SDK:**

| Файл | Изменение |
|---|---|
| `src/Application/Contracts/ApplicationInstallations/Entity/ApplicationInstallationStatus.php` | Добавить `case needReinstall = 'needReinstall'` |
| `src/Application/Contracts/ApplicationInstallations/Entity/ApplicationInstallationInterface.php` | Добавить метод `markAsNeedReinstall(?string $comment): void` + обновить docblock `applicationUninstalled` |
| `src/Application/Contracts/ApplicationInstallations/Events/ApplicationInstallationMarkedNeedReinstallEvent.php` | Новое событие (id, updatedAt, comment) |

#### PR 2: bitrix24-php-lib (этот репозиторий)

**Домен — `src/ApplicationInstallations/Entity/ApplicationInstallation.php`:**

| Изменение | Детали |
|---|---|
| `markAsNeedReinstall(?string $comment)` | Переход `new → needReinstall`, emit `ApplicationInstallationMarkedNeedReinstallEvent` |
| Guard в `applicationUninstalled()` | Добавить `needReinstall` в допустимые статусы |

**Событие — `src/ApplicationInstallations/Entity/ApplicationInstallationMarkedNeedReinstallEvent.php`:**

```php
readonly class ApplicationInstallationMarkedNeedReinstallEvent {
    public function __construct(
        public Uuid $id,
        public CarbonImmutable $updatedAt,
        public ?string $comment
    ) {}
}
```

**Repository — `src/ApplicationInstallations/Infrastructure/Repository/StaleInstallationFinderInterface.php`:**

```php
interface StaleInstallationFinderInterface {
    public function findStaleInstallations(
        ApplicationInstallationStatus $status,
        CarbonImmutable $olderThan,
        ?string $memberId = null
    ): array;
}
```

**Repository — `src/ApplicationInstallations/Infrastructure/Doctrine/StaleInstallationFinder.php`:**

- JOIN к `Bitrix24Account` для memberId
- WHERE `status = :status AND createdAt < :olderThan`
- Опциональный фильтр `AND b24.memberId = :memberId`

**Repository — `tests/Helpers/ApplicationInstallations/InMemoryStaleInstallationFinder.php`:**

- In-memory реализация `StaleInstallationFinderInterface` для тестов

**UseCase — `src/ApplicationInstallations/UseCase/MarkOldInstallations/`:**

| Файл | Содержание |
|---|---|
| `Command.php` | `public function __construct(public int $ttlInSeconds = self::DEFAULT_TTL)` + `DEFAULT_TTL = 3600` |
| `Handler.php` | Находит зависшие через `StaleInstallationFinderInterface`, вызывает `markAsNeedReinstall()`, сохраняет, flush |

**Console — `src/Console/MarkOldInstallationsCommand.php`:**

```php
protected static $defaultName = 'bitrix24:installations:mark-old';
// --ttl=3600 (опционально, дефолт из Command::DEFAULT_TTL)
```

**Документация — `src/ApplicationInstallations/Docs/application-installations.md`:**

- Добавить секцию «Stale Installation Cleanup» с описанием flow
- Sequence diagram для markOldInstallations
- Sequence diagram для reinstall после needReinstall
- Обновить секцию Follow-Up — отметить что issue #92 реализован

**CHANGELOG.md:**

- Новая запись: feature — mark old installations as needReinstall via background command

### Test Cases

#### Unit Tests

**MarkOldInstallations/HandlerTest:**
1. Находит одну зависшую установку → переводит в `needReinstall`, событие диспатчится
2. Находит несколько зависших → все переведены
3. Нет зависших → ничего не делает, flush не вызван
4. TTL = 0 → находит все `new` установки

**MarkOldInstallations/CommandTest:**
1. Дефолтный TTL = 3600
2. Кастомный TTL передаётся корректно

**Entity/ApplicationInstallation — markAsNeedReinstall:**
1. `new → needReinstall` — успех, событие создано
2. `active → needReinstall` — exception
3. `blocked → needReinstall` — exception
4. `needReinstall → deleted` через `applicationUninstalled(null)` — успех

#### Functional Tests

**MarkOldInstallations/HandlerTest:**
1. Создаём `new` установку с `createdAt = NOW() - 2h`, запускаем с TTL=3600 → статус `needReinstall`
2. Создаём `new` установку с `createdAt = NOW() - 30m`, запускаем с TTL=3600 → статус остаётся `new`
3. Запускаем Install повторно → старая `needReinstall` установка удаляется, новая создаётся

### Assumptions

- `needReinstall` добавляется только в `ApplicationInstallationStatus`, не в `Bitrix24AccountStatus`
- Мастер-аккаунт при markOld не меняет статус — остаётся в `new`
- `applicationUninstalled(null)` из `needReinstall → deleted` не требует блокировки
- Метод `findStaleInstallations` сначала живёт в локальном репозитории этой библиотеки, потом переносится в SDK-интерфейс отдельным PR
- Scheduling (cron/worker) — ответственность потребителя библиотеки
- Константа TTL по умолчанию = 3600 секунд, находится в Command
