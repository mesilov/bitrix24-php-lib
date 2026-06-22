# Сценарии импорта партнёров

## Обзор

- **Ключ совпадения:** `bitrix24_partner_number`
- Команда: `bitrix24:partners:import`
- CSV-формат см. в [partner-common.md](partner-common.md)
- CSV генерируется командой `partners:scrape`, поэтому ошибки формата маловероятны

---

## Опции команды

| Опция | Значения | По умолчанию | Описание |
|-------|----------|--------------|----------|
| `file` (аргумент) | путь | — | Путь к CSV файлу. Обязательный. Абсолютный или относительный (от директории запуска команды) |
| `--sync-mode` | `full`, `partial` | `full` | full — CSV = источник истины, partial — CSV = патч |
| `--dry-run` | — | `false` | Показать что произойдёт без реальных изменений в БД |

---

## Сценарии

### Полная синхронизация (`--sync-mode=full`, по умолчанию)

CSV = полный источник истины. Действия: **создание**, **обновление**, **пропуск**, **софт-делит**.

```bash
php bin/console bitrix24:partners:import var/scraper/partners.csv
```

### Частичное обновление (`--sync-mode=partial`)

CSV = патч только для указанных партнёров. Действия: **создание**, **обновление**, **пропуск**.

```bash
# Шаг 1: Скрейпить нужных партнёров
php bin/console partners:scrape --output-dir=var/scraper --partner-ids=3240,5859557

# Шаг 2: Импортировать результат (partial — не трогает остальных)
php bin/console bitrix24:partners:import var/scraper/partners.csv --sync-mode=partial
```

### Предварительная проверка (`--dry-run`)

Показывает план действий без реальных изменений.

```bash
php bin/console bitrix24:partners:import var/scraper/partners.csv --dry-run
```

```
DRY RUN — изменения не применены

  CREATE      #99999 NewPartner
  UPDATE      #3240  Hoster.KZ (title, phone)
  SOFT-DELETE #15549800 OldCorp
  ...

Created: 15 | Updated: 42 | Skipped: 893 | Soft-deleted: 50 | Errors: 0
```

---

