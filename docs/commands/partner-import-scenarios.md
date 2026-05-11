# Сценарии импорта партнёров Bitrix24

## Суть

CSV файл — источник истины. Импорт синхронизирует БД с CSV. Ключ совпадения — `bitrix24_partner_number`.

## Четыре сценария

| Ситуация | Действие | Управление |
|---|---|---|
| В CSV и БД, данные совпадают | **Пропуск** | Автоматически |
| В CSV и БД, данные отличаются | **Обновление** или пропуск | `--strategy-update=replace` |
| В CSV, нет в БД | **Создание** | Автоматически |
| В БД, нет в CSV | **Мягкое удаление** или пропуск | `--strategy-delete=soft-delete` |

## Безопасные дефолты

По умолчанию команда создаёт новых партнёров и пропускает остальные случаи. Для обновления и удаления нужно явно указать флаги.

## Примеры

```bash
# Базовый — только создание новых партнёров
php bin/console bitrix24:partners:import partners.csv

# Полная синхронизация — создать, обновить, удалить лишних
php bin/console bitrix24:partners:import partners.csv --strategy-update=replace --strategy-delete=soft-delete

# Проверка без изменений
php bin/console bitrix24:partners:import partners.csv --strategy-update=replace --strategy-delete=soft-delete --dry-run
```

## Отчёт

```
Import Results:
  Created:      15
  Updated:      42
  Skipped:      893
  Soft-deleted: 50
  Errors:       0
```

В режиме `--dry-run` — сводка + детали по изменимым записям (create, update, delete), без unchanged.
