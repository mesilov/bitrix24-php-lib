# План: Upsert-логика для импорта партнёров

## Контекст

Команда `bitrix24:partners:import` сейчас только создаёт новых партнёров. Если партнёр с таким `bitrix24_partner_number` уже есть — CreateHandler выбрасывает исключение. Нужно доработать импорт чтобы поддерживать 4 сценария: пропуск, обновление, создание, мягкое удаление.

Подробная спецификация: `docs/dev/partner-import-scenarios.md`
Краткая презентация: `docs/commands/partner-import-scenarios.md`

---

## Задача 1: Создать `UseCase\Upsert\Command`

**Файл:** `src/Bitrix24Partners/UseCase/Upsert/Command.php`

- Поля: `title`, `bitrix24PartnerNumber`, `site`, `phone` (PhoneNumber), `email`, `openLineId`, `externalId`, `logoUrl`
- Валидация: как в текущем `Create\Command`
- Readonly class

---

## Задача 2: Создать `UseCase\Upsert\Handler`

**Файл:** `src/Bitrix24Partners/UseCase/Upsert/Handler.php`

**Зависимости:**
- `Bitrix24PartnerRepositoryInterface`
- `Flusher`
- `PhoneNumberUtil`
- `LoggerInterface`

**Логика:**
```
1. Лог: Bitrix24Partners.Upsert.start
2. findByBitrix24PartnerNumber(command.bitrix24PartnerNumber)
3. Если партнёр найден:
   a. Сравнить поля: title, site, phone, email, openLineId, externalId, logoUrl
   b. Если есть отличия — вызвать методы change*() на сущности
   c. Если отличий нет — skip, лог "no changes"
   d. save + flush
   e. Лог: Bitrix24Partners.Upsert.updated
4. Если партнёр НЕ найден:
   a. Валидация phone если передан
   b. new Bitrix24Partner(Uuid::v7(), ...) — как в текущем Create\Handler
   c. save + flush
   d. Лог: Bitrix24Partners.Upsert.created
5. finally: Лог Bitrix24Partners.Upsert.finish
```

**Важно:** Метод handler'а должен возвращать результат (enum или string) — `created`, `updated`, `skipped` — чтобы команда могла формировать отчёт.

---

## Задача 3: Переписать `ImportPartnersCsvCommand`

**Файл:** `src/Bitrix24Partners/Console/ImportPartnersCsvCommand.php`

**Зависимости:**
- `Upsert\Handler` (вместо `Create\Handler`)
- `Delete\Handler` (для soft-delete)
- `Bitrix24PartnerRepositoryInterface` (для загрузки всех партнёров из БД)
- `PhoneNumberUtil`

**Новые опции:**

| Опция | Значения | По умолчанию |
|---|---|---|
| `--strategy-update` | `skip`, `replace` | `skip` |
| `--strategy-delete` | `skip`, `soft-delete` | `skip` |
| `--dry-run` | — | `false` |
| `--skip-errors` / `-s` | — | `false` |

**Алгоритм:**
```
1. Прочитать CSV → map по bitrix24_partner_number
2. Загрузить всех активных (не deleted) партнёров из БД → map по bitrix24_partner_number
3. Инициализировать счётчики: created, updated, skipped, softDeleted, errors
4. Для каждого партнёра в CSV:
   a. Найти в DB map по bitrix24_partner_number
   b. Если найден:
      - Сравнить данные (title, site, phone, email, openLineId, externalId, logoUrl)
      - Если данные совпадают → skipped++, лог "unchanged"
      - Если данные отличаются:
        - strategy-update=skip → skipped++, лог "data differs, skip update"
        - strategy-update=replace → вызвать Upsert\Handler → updated++
   c. Если не найден → вызвать Upsert\Handler → created++
   d. В режиме dry-run — не вызывать handler'ы, только считать
5. Для каждого партнёра в БД, которого нет в CSV:
   a. strategy-delete=skip → skipped++
   b. strategy-delete=soft-delete:
      - dry-run: softDeleted++ с деталями
      - обычный режим: вызвать Delete\Handler → softDeleted++
6. Вывести отчёт
7. В режиме dry-run: сводка + детали по create/update/delete (без unchanged)
```

**Отчёт (обычный режим):**
```
Import Results:
  Created:      15
  Updated:      42
  Skipped:      893
  Soft-deleted: 50
  Errors:       0
```

**Отчёт (dry-run):**
```
Dry-run Results:
  Would create:      15
  Would update:      42
  Would skip:        893
  Would soft-delete: 50
  Errors:            0

Planned actions:
  CREATE      #99999 NewPartner
  UPDATE      #3240  Hoster.KZ (title, phone)
  UPDATE      #5855  Corp Ltd (email)
  SOFT-DELETE #15549800 OldCorp
  ... (только create, update, delete — без unchanged)
```

---

## Задача 4: Добавить метод в `Bitrix24PartnerRepositoryInterface`

Если для загрузки всех активных партнёров нужен метод `findAllActive()` — добавить в интерфейс и реализацию.

Или использовать DQL напрямую в команде через EntityManager. Зависит от предпочтений — обсудить при реализации.

**Вариант 1 (рекомендуется):** Добавить `findAllActive(): array` в репозиторий — чистый DDD.
**Вариант 2:** DQL в команде — быстрее, но нарушает инкапсуляцию репозитория.

---

## Задача 5: Обновить документацию

- `docs/commands/partner-commands.md` — обновить секцию `bitrix24:partners:import` (новые опции, новые примеры)
- `docs/commands/partner-import-scenarios.md` — без изменений (уже актуальна)

---

## Задача 6: Тесты

- Unit-тест для `Upsert\Handler`:
  - Создание нового партнёра (не найден в БД)
  - Обновление существующего (найден, данные отличаются)
  - Пропуск (найден, данные совпадают)
- Обновить/дописать тесты `ImportPartnersCsvCommand`:
  - Базовый импорт (только создание)
  - Импорт с --strategy-update=replace
  - Импорт с --strategy-delete=soft-delete
  - Dry-run режим
  - Пропуск ошибок (--skip-errors)

---

## Что НЕ меняется

- `UseCase\Create\Handler` — остаётся для других потребителей (API, тесты)
- `UseCase\Update\Handler` — остаётся для обновления по UUID
- `UseCase\Delete\Handler` — остаётся как есть
- Сущность `Bitrix24Partner` — без изменений
- CSV-формат — без изменений

---

## Порядок выполнения

1. Задача 1 (Upsert\Command)
2. Задача 2 (Upsert\Handler)
3. Задача 4 (findAllActive в репозитории)
4. Задача 3 (ImportPartnersCsvCommand)
5. Задача 6 (тесты)
6. Задача 5 (документация)
7. `make lint-phpstan && make test-run-unit`
