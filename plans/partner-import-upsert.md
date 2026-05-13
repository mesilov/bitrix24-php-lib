# План: Upsert-логика для импорта партнёров

## Контекст

Команда `bitrix24:partners:import` сейчас только создаёт новых партнёров. Если партнёр с таким `bitrix24_partner_number` уже есть — CreateHandler выбрасывает исключение. Нужно доработать импорт чтобы поддерживать 4 сценария: создание, обновление, пропуск, мягкое удаление.

Два режима работы:
- **full** (дефолт) — CSV = полная выгрузка, отсутствующие в CSV партнёры помечаются как удалённые
- **partial** — CSV = патч, обновить/создать только то что в файле, остальное не трогается

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

**Важно:** Handler возвращает `void`. Команда сама определяет create/update/skip по своей DB map до вызова handler'а. Handler не занимается подсчётом статистики.

**Логика:**
```
1. Лог: Bitrix24Partners.Upsert.start
2. findByBitrix24PartnerNumber(command.bitrix24PartnerNumber)
3. Если партнёр найден:
   a. Сравнить поля: title, site, phone, email, openLineId, externalId, logoUrl
   b. Если есть отличия — вызвать методы change*() на сущности
   c. Если отличий нет — лог "no changes", return
   d. save + flush
   e. Лог: Bitrix24Partners.Upsert.updated
4. Если партнёр НЕ найден:
   a. Валидация phone если передан
   b. new Bitrix24Partner(Uuid::v7(), ...) — как в текущем Create\Handler
   c. save + flush
   d. Лог: Bitrix24Partners.Upsert.created
5. finally: Лог Bitrix24Partners.Upsert.finish
```

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
| `--sync-mode` | `full`, `partial` | `full` |
| `--dry-run` | — | `false` |
| `--skip-errors` / `-s` | — | `false` |

**Алгоритм:**
```
1. Прочитать CSV → map по bitrix24_partner_number
2. Загрузить всех активных (не deleted) партнёров из БД → map по bitrix24_partner_number
3. Инициализировать счётчики: created, updated, skipped, softDeleted, errors
4. Для каждого партнёра в CSV:
   a. Найти в DB map по bitrix24_partner_number
   b. Если не найден → Upsert\Handler (создание) → created++
   c. Если найден:
      - Сравнить данные (title, site, phone, email, openLineId, externalId, logoUrl)
      - Если данные совпадают → skipped++
      - Если данные отличаются → Upsert\Handler (обновление) → updated++
   d. В режиме dry-run — не вызывать handler'ы, только считать
5. Если sync-mode=full:
   Для каждого партнёра в БД, которого нет в CSV:
   a. dry-run: softDeleted++ с деталями
   b. обычный режим: вызвать Delete\Handler → softDeleted++
6. Если sync-mode=partial — не трогаем отсутствующих в CSV
7. Вывести отчёт
8. В режиме dry-run: сводка + детали по create/update/delete (без unchanged)
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

## ~~Задача 5: Обновить документацию~~ — ВЫПОЛНЕНО

Обновлены файлы:
- `docs/commands/partner-import-scenarios.md` — новые сценарии, sync-mode
- `docs/dev/partner-import-scenarios.md` — переработана техспека
- `docs/commands/partner-commands.md` — обновлена секция импорта

---

## Задача 6: Тесты

- Unit-тест для `Upsert\Handler`:
  - Создание нового партнёра (не найден в БД)
  - Обновление существующего (найден, данные отличаются)
  - Пропуск (найден, данные совпадают)
- Обновить/дописать тесты `ImportPartnersCsvCommand`:
  - Полная синхронизация (--sync-mode=full)
  - Частичное обновление (--sync-mode=partial)
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
6. `make lint-phpstan && make test-run-unit`
