# Сценарии скрейпинга партнёров

## Обзор

Единая команда `partners:scrape` работает в двух режимах:

- **Полный парсинг** — обход всех страниц каталога партнёров зоны
- **Обновление по ID** (с `--partner-ids`) — скрейп детальных страниц только указанных партнёров

Результат — CSV-файл в `--output-dir` с автоматическим именем `partners-YYYYMMDD-HHMMSS.csv`. Формат CSV см. в [partner-common.md](partner-common.md).

---

## Опции команды

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--zone` | Зона Bitrix24: `ru`, `kz` | `ru` |
| `--output-dir` | Путь к директории для CSV файлов (абсолютный или относительный от корня проекта). **Обязательный** | — |
| `--request-delay` | Задержка между HTTP-запросами (сек) | `2` |
| `--partner-ids` | ID партнёров через запятую (режим обновления) | — |
| `--resume` | Продолжить с места обрыва (из state.json) | `false` |
| `--insecure` | Отключить проверку SSL (для dev) | `false` |

---

## Сценарии

### Полный парсинг зоны

Обходит все страницы каталога и скрейпит детальные карточки каждого партнёра.

```bash
php bin/console partners:scrape --output-dir=var/scraper
```

### Resume после обрыва

При обрыве создаётся `state.json`. С `--resume` парсинг продолжится с последней обработанной страницы.

```bash
php bin/console partners:scrape --output-dir=var/scraper --resume
```

### Обновление конкретных партнёров по ID

Скрейпит только указанных партнёров. Результат — отдельный CSV-файл. При импорте используйте `--sync-mode=partial` (см. [partner-import-scenarios.md](partner-import-scenarios.md)).

```bash
php bin/console partners:scrape --output-dir=var/scraper --partner-ids=3240,5859557
```
