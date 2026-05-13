# Сценарии импорта партнёров — техническая спецификация

> **Замечание:** Этот файл предназначен для разработки. После реализации кода файл можно удалить.

## Обзор

CSV файл является источником истины. Команда импорта синхронизирует состояние БД с данными из CSV файла.

- **Ключ совпадения:** `bitrix24_partner_number`
- Один партнёр однозначно определяется по номеру партнёра в Bitrix24
- Команда: `bitrix24:partners:import`

## Четыре сценария импорта

### Сценарий 1: Создание нового партнёра

**Условие:** Партнёр есть в CSV, но отсутствует в БД.

**Действие:** Создать нового партнёра с данными из CSV. Партнёр создаётся в статусе `active`.

**Используемый UseCase:** `Bitrix24Partners\UseCase\Create\Handler`

**Обязательные поля:** `title`, `bitrix24_partner_number`

**Опциональные поля:** `site`, `phone`, `email`, `open_line_id`, `external_id`, `logo_url`

**Пример:**
```
CSV: #99999, NewPartner, https://new.com
БД:  (нет записи)
→ CREATE
```

### Сценарий 2: Обновление данных

**Условие:** Партнёр есть в CSV и в БД, но данные отличаются.

**Действие:** Обновить все изменившиеся поля партнёра данными из CSV. Поля сравниваются по одному, обновляются только изменившиеся.

**Используемый UseCase:** `Bitrix24Partners\UseCase\Update\Handler`

**Сравниваемые поля:** `title`, `site`, `phone`, `email`, `openLineId`, `externalId`, `logoUrl`

**Пример:**
```
CSV: #3240, Hoster.KZ NEW, https://b24.kz
БД:  #3240, Hoster.KZ, https://b24.kz
→ UPDATE (title изменился)
```

### Сценарий 3: Пропуск (данные совпадают)

**Условие:** Партнёр есть в CSV и в БД, данные полностью совпадают.

**Действие:** Ничего не делать. Партнёр пропускается.

**Используемый UseCase:** — (нет вызовов)

**Пример:**
```
CSV: #3240, Hoster.KZ, https://b24.kz
БД:  #3240, Hoster.KZ, https://b24.kz
→ SKIP
```

### Сценарий 4: Мягкое удаление

**Условие:** Партнёр есть в БД, но отсутствует в CSV. Срабатывает **только** при `--sync-mode=full`.

**Действие:** Пометить партнёра как удалённого (мягкое удаление). Запись остаётся в БД со статусом `deleted`. Физического удаления не происходит.

**Используемый UseCase:** `Bitrix24Partners\UseCase\Delete\Handler` (вызывает `markAsDeleted()`)

**Пример:**
```
CSV: (нет записи)
БД:  #15549800, OldCorp

--sync-mode=full    → SOFT-DELETE
--sync-mode=partial → SKIP (не трогаем)
```

---

## Алгоритм команды

```
1. Прочитать CSV → map по bitrix24_partner_number
2. Загрузить всех активных (не deleted) партнёров из БД → map по bitrix24_partner_number
3. Для каждого партнёра в CSV:
   a. Если нет в БД → создание (сценарий 1)
   b. Если есть в БД и данные отличаются → обновление (сценарий 2)
   c. Если есть в БД и данные совпадают → пропуск (сценарий 3)
4. Если sync-mode=full:
   Для каждого партнёра в БД, которого нет в CSV → мягкое удаление (сценарий 4)
```

---

## Опции команды

| Опция | Значения | По умолчанию | Описание |
|-------|----------|--------------|----------|
| `file` (аргумент) | путь | — | Путь к CSV файлу (обязательный) |
| `--sync-mode` | `full`, `partial` | `full` | Режим синхронизации: full — CSV = источник истины, partial — CSV = патч |
| `--dry-run` | — | `false` | Показать что произойдёт без реальных изменений в БД |
| `--skip-errors` / `-s` | — | `false` | Пропускать строки с ошибками и продолжать |

---

## Таблица решений

| # | Партнёр в CSV | Партнёр в БД | Данные | sync-mode | Действие |
|---|---|---|---|---|---|
| 1 | Да | Нет | — | любой | **Создание** |
| 2 | Да | Да | Отличаются | любой | **Обновление** |
| 3 | Да | Да | Совпадают | любой | **Пропуск** |
| 4 | Нет | Да | — | `full` | **Мягкое удаление** |
| 5 | Нет | Да | — | `partial` | **Пропуск** |

---

## Отчёт после выполнения

Команда выводит сводку:

```
Import Results:
  Created:      15
  Updated:      42
  Skipped:      893
  Soft-deleted: 50
  Errors:       0
```

---

## Отчёт в режиме dry-run

Сводка + детали по действиям (без unchanged):

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
  SOFT-DELETE #9002 LegacyPartner
  ... (только create, update, delete — без unchanged)
```

---

## Примеры использования

### Полная синхронизация (дефолт)

```bash
php bin/console bitrix24:partners:import partners.csv
```

Создаёт новых, обновляет существующих с изменившимися данными, помечает как удалённые отсутствующих в CSV.

### Частичное обновление (partial)

```bash
php bin/console bitrix24:partners:import partners_update.csv --sync-mode=partial
```

Создаёт новых и обновляет существующих только из указанного файла. Партнёры, отсутствующие в файле, не затрагиваются. Подходит для точечного обновления нескольких партнёров.

Workflow частичного обновления:

```bash
# Шаг 1: Скрейпить нужных партнёров с сайта
php bin/console partners:update --partner-ids=3240,5859557 --output-file=partners_update.csv

# Шаг 2: Импортировать результат в БД (partial — не трогает остальных)
php bin/console bitrix24:partners:import partners_update.csv --sync-mode=partial
```

### Предварительная проверка (dry-run)

```bash
php bin/console bitrix24:partners:import partners.csv --dry-run
```

Показывает что произойдёт без реальных изменений.

---

## CSV-формат

Совпадает с форматом из `docs/commands/partner-commands.md`:

```
bitrix24_partner_number,title,site,phone,email,logo_url,detail_page_url,base_domain,scraped_at
3240,Hoster.KZ,https://b24.kz/,8-727-2-379-284,info@b24.kz,https://.../logo.jpg,/partners/partner/3240/,https://www.bitrix24.kz,2026-05-01T12:27:22+00:00
```

**Обязательные колонки:** `title`, `bitrix24_partner_number`
**Опциональные колонки:** `site`, `phone`, `email`, `open_line_id`, `external_id`, `logo_url`
