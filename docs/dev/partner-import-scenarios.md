# Сценарии импорта партнёров — техническая спецификация

> **Замечание:** Этот файл предназначен для разработки. После реализации кода файл можно удалить.

## Обзор

CSV файл является источником истины. Команда импорта синхронизирует состояние БД с данными из CSV файла.

- **Ключ совпадения:** `bitrix24_partner_number`
- Один партнёр однозначно определяется по номеру партнёра в Bitrix24
- Команда: `bitrix24:partners:import`

## Четыре сценария импорта

### Сценарий 1: Пропуск (данные совпадают)

**Условие:** Партнёр есть в CSV и в БД, данные полностью совпадают.

**Действие:** Ничего не делать. Партнёр пропускается.

**Используемый UseCase:** — (нет вызовов)

**Пример:**
```
CSV: #3240, Hoster.KZ, https://b24.kz
БД:  #3240, Hoster.KZ, https://b24.kz
→ SKIP
```

### Сценарий 2: Обновление данных

**Условие:** Партнёр есть в CSV и в БД, но данные отличаются.

**Действие по умолчанию:** Пропуск (`--strategy-update=skip`).

**Действие при `--strategy-update=replace`:** Обновить все изменившиеся поля партнёра данными из CSV. Поля сравниваются по одному, обновляются только изменившиеся.

**Используемый UseCase:** `Bitrix24Partners\UseCase\Upsert\Handler` (ветка обновления)

**Сравниваемые поля:** `title`, `site`, `phone`, `email`, `openLineId`, `externalId`, `logoUrl`

**Пример:**
```
CSV: #3240, Hoster.KZ NEW, https://b24.kz
БД:  #3240, Hoster.KZ, https://b24.kz

--strategy-update=skip    → SKIP (изменения игнорируются)
--strategy-update=replace → UPDATE (title изменился)
```

### Сценарий 3: Добавление нового партнёра

**Условие:** Партнёр есть в CSV, но отсутствует в БД.

**Действие:** Создать нового партнёра с данными из CSV. Партнёр создаётся в статусе `active`.

**Используемый UseCase:** `Bitrix24Partners\UseCase\Upsert\Handler` (ветка создания)

**Обязательные поля:** `title`, `bitrix24_partner_number`

**Опциональные поля:** `site`, `phone`, `email`, `open_line_id`, `external_id`, `logo_url`

**Пример:**
```
CSV: #99999, NewPartner, https://new.com
БД:  (нет записи)
→ CREATE
```

### Сценарий 4: Удаление лишнего

**Условие:** Партнёр есть в БД, но отсутствует в CSV.

**Действие по умолчанию:** Пропуск (`--strategy-delete=skip`).

**Действие при `--strategy-delete=soft-delete`:** Пометить партнёра как удалённого (мягкое удаление). Запись остаётся в БД со статусом `deleted`. Физического удаления не происходит.

**Используемый UseCase:** `Bitrix24Partners\UseCase\Delete\Handler` (вызывает `markAsDeleted()`)

**Пример:**
```
CSV: (нет записи)
БД:  #15549800, OldCorp

--strategy-delete=skip       → SKIP
--strategy-delete=soft-delete → SOFT-DELETE
```

---

## Алгоритм команды

```
1. Прочитать CSV → map по bitrix24_partner_number
2. Загрузить всех активных (не deleted) партнёров из БД → map по bitrix24_partner_number
3. Для каждого партнёра в CSV:
   a. Если есть в БД и данные совпадают → пропуск (сценарий 1)
   b. Если есть в БД и данные отличаются → зависит от --strategy-update (сценарий 2)
   c. Если нет в БД → создание (сценарий 3)
4. Для каждого партнёра в БД, которого нет в CSV:
   a. Зависит от --strategy-delete (сценарий 4)
```

---

## Опции команды

| Опция | Значения | По умолчанию | Описание |
|-------|----------|--------------|----------|
| `file` (аргумент) | путь | — | Путь к CSV файлу (обязательный) |
| `--strategy-update` | `skip`, `replace` | `skip` | Что делать при расхождении данных с БД |
| `--strategy-delete` | `skip`, `soft-delete` | `skip` | Что делать с партнёрами, отсутствующими в CSV |
| `--dry-run` | — | `false` | Показать что произойдёт без реальных изменений в БД |
| `--skip-errors` / `-s` | — | `false` | Пропускать строки с ошибками и продолжать |

---

## Таблица решений

| Партнёр в CSV | Партнёр в БД | Данные | --strategy-update | --strategy-delete | Действие |
|---|---|---|---|---|---|
| Да | Да | Совпадают | *любой* | *любой* | **Пропуск** |
| Да | Да | Отличаются | `skip` | *любой* | **Пропуск** |
| Да | Да | Отличаются | `replace` | *любой* | **Обновление** |
| Да | Нет | — | *любой* | *любой* | **Создание** |
| Нет | Да | — | *любой* | `skip` | **Пропуск** |
| Нет | Да | — | *любой* | `soft-delete` | **Мягкое удаление** |

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

### Базовый импорт (только создание новых)

```bash
php bin/console bitrix24:partners:import partners.csv
```

Создаёт новых партнёров. Существующие — пропускаются. Отсутствующие в CSV — остаются в БД.

### Обновление без удаления

```bash
php bin/console bitrix24:partners:import partners.csv --strategy-update=replace
```

Создаёт новых, обновляет существующих. Отсутствующие в CSV — пропускаются.

### Полная синхронизация

```bash
php bin/console bitrix24:partners:import partners.csv --strategy-update=replace --strategy-delete=soft-delete
```

Создаёт новых, обновляет существующих, помечает как удалённые отсутствующих в CSV.

### Предварительная проверка (dry-run)

```bash
php bin/console bitrix24:partners:import partners.csv --strategy-update=replace --strategy-delete=soft-delete --dry-run
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
