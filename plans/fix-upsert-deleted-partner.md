# Fix: Upsert не видит deleted-партнёров

## Проблема

При импорте CSV с партнёрами, которые ранее были помечены как `deleted`,
получаем unique constraint violation:

```
SQLSTATE[23505]: Unique violation: duplicate key "b24_partner_number"
```

### Причина

1. `Upsert\Handler::handle()` вызывает `findByBitrix24PartnerNumber()`
2. Репозиторий фильтрует по `p.status != deleted` — не находит партнёра
3. Handler думает "партнёра нет" → вызывает `create()`
4. INSERT → unique constraint violation по `b24_partner_number`

### Воспроизведение

```bash
# Шаг 1: Полный импорт (партнёр #16592200 создаётся)
php bin/console bitrix24:partners:import partners.csv

# Шаг 2: Полный импорт без этого партнёра (→ soft-delete)
php bin/console bitrix24:partners:import partners_subset.csv  # #16592200 отсутствует

# Шаг 3: Попытка обновить этого партнёра → ОШИБКА
php bin/console partners:update --partner-ids=16592200
php bin/console bitrix24:partners:import partners_update.csv --sync-mode=partial
```

### Контекст

- Сущность `Bitrix24Partner` имеет `markAsActive()` — но он разрешает только `blocked → active`, **не** `deleted → active`
- `deleted` = окончательное удаление по бизнес-логике (восстановление возможно только из `blocked`)
- Поэтому нельзя просто "оживить" deleted-запись через существующий метод

---

## Варианты решения

### Вариант A: Физическое удаление + создание нового

```
deleted-запись → DELETE из БД → INSERT новой записи (новый UUID, тот же b24_partner_number)
```

**Плюсы:**
- Простая реализация
- Партнёр снова актуален, данные свежие
- Не нарушает бизнес-правило "deleted = окончательно" (старая запись реально удалена)

**Минусы:**
- Теряется история (UUID, created_at, события)
- Если на старый UUID есть ссылки в других bounded contexts — они сломаются

**Правки:**
1. **Репозиторий** — добавить метод `findBitrix24PartnerNumberAll()` без фильтра статуса
2. **Upsert\Handler** — если `findByBitrix24PartnerNumber()` вернул null → вызвать `findBitrix24PartnerNumberAll()`, если found + deleted → `entityManager->remove()` + flush → затем `create()`

**Тесты:**
- upsert deleted-партнёра → старая запись удалена, создана новая (новый UUID)
- upsert нового партнёра → create как раньше
- upsert активного партнёра → update как раньше

---

### Вариант B: Пропуск + warning

```
deleted-запись → пропустить, не трогать → warning в лог
```

**Плюсы:**
- `deleted` = окончательно, нет побочных эффектов
- Самая простая реализация
- Нет риска сломать ссылки на UUID

**Минусы:**
- Данные не обновятся — если партнёр реально вернулся, он останется `deleted`
- Нужно ручное вмешательство (например, физически удалить старую запись из БД и повторить импорт)

**Правки:**
1. **Репозиторий** — добавить метод `findBitrix24PartnerNumberAll()` без фильтра статуса
2. **Upsert\Handler** — если `findBitrix24PartnerNumberAll()` found + deleted → логировать warning ("партнёр #N в статусе deleted, upsert пропущен") и return

**Тесты:**
- upsert deleted-партнёра → skipped, warning в логе, БД не изменена
- upsert нового партнёра → create как раньше
- upsert активного партнёра → update как раньше

---

### Вариант C: Прямое обновление статуса (обойти markAsActive)

```
deleted-запись → напрямую установить status = active → updateIfNeeded()
```

**Плюсы:**
- Сохраняется история, UUID, created_at
- Партнёр "оживает" на месте — нет проблем со ссылками

**Минусы:**
- Нарушает бизнес-правило "deleted = окончательно"
- Нужно менять гард в `markAsActive()` (разрешить deleted→active) или писать отдельный метод `forceReactivate()` — нарушение инкапсуляции
- Событие `Bitrix24PartnerUnblockedEvent` семантически не подходит (это не unblock, а reactivate)

**Правки:**
1. **Репозиторий** — добавить метод `findBitrix24PartnerNumberAll()` без фильтра статуса
2. **Сущность** — новый метод `forceReactivate(?string $comment)` без проверки исходного статуса, генерирует `Bitrix24PartnerReactivatedEvent`
3. **Событие** — новый класс `Bitrix24PartnerReactivatedEvent`
4. **Upsert\Handler** — если found + deleted → `forceReactivate()` + `updateIfNeeded()`

**Тесты:**
- upsert deleted-партнёра → статус active, данные обновлены, UUID сохранён
- upsert нового партнёра → create как раньше
- upsert активного партнёра → update как раньше
- `forceReactivate()` из active → ошибка (или нет, зависит от решения)
