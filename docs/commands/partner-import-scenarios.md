# Сценарии импорта партнёров

## Обзор

CSV файл является источником истины. Команда импорта синхронизирует состояние БД с данными из CSV файла.

- **Ключ совпадения:** `bitrix24_partner_number`
- Команда: `bitrix24:partners:import`
- CSV-формат см. в [partner-common.md](partner-common.md)

---

## Опции команды

| Опция | Значения | По умолчанию | Описание |
|-------|----------|--------------|----------|
| `file` (аргумент) | путь | — | Путь к CSV файлу (обязательный). Если без пути — ищется в `var/scraper/` |
| `--sync-mode` | `full`, `partial` | `full` | full — CSV = источник истины, partial — CSV = патч |
| `--dry-run` | — | `false` | Показать что произойдёт без реальных изменений в БД |
| `--skip-errors` / `-s` | — | `false` | Пропускать строки с ошибками и продолжать |

---

## Сценарии

### Создание нового партнёра

Партнёр есть в CSV, но отсутствует в БД. Создаётся в статусе `active`.

### Обновление данных

Партнёр есть в CSV и в БД, данные отличаются. Обновляются только изменившиеся поля.

### Пропуск (данные совпадают)

Партнёр есть в CSV и в БД, данные полностью совпадают. Запись не модифицируется.

### Софт-делит

Партнёр есть в БД, но отсутствует в CSV. Срабатывает **только** при `--sync-mode=full`. Запись помечается как `deleted`, физического удаления не происходит. При `--sync-mode=partial` — пропускается.

---

## Отчёт после выполнения

```
Created: 15 | Updated: 42 | Skipped: 893 | Soft-deleted: 50 | Errors: 0
```

---

## Отчёт в режиме dry-run

Сводка + список плановых действий (без unchanged):

```
DRY RUN — изменения не применены

Planned actions:
  CREATE      #99999 NewPartner
  UPDATE      #3240  Hoster.KZ (title, phone)
  SOFT-DELETE #15549800 OldCorp
  ...

Created: 15 | Updated: 42 | Skipped: 893 | Soft-deleted: 50 | Errors: 0
```

---

## Примеры использования

### Полная синхронизация (дефолт)

```bash
php bin/console bitrix24:partners:import var/scraper/partners-20260527-143000.csv
```

Создаёт новых, обновляет изменившихся, помечает софт-делит отсутствующих в CSV.

### Частичное обновление (partial)

```bash
# Шаг 1: Скрейпить нужных партнёров
php bin/console partners:scrape --partner-ids=3240,5859557

# Шаг 2: Импортировать результат (partial — не трогает остальных)
php bin/console bitrix24:partners:import var/scraper/partners-20260527-150000.csv --sync-mode=partial
```

### Предварительная проверка (dry-run)

```bash
php bin/console bitrix24:partners:import var/scraper/partners-20260527-143000.csv --dry-run
```
