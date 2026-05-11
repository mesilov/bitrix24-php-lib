# Консольные команды для работы с партнёрами Bitrix24

## Обзор

Архитектура разделена на следующие компоненты:

```
Console/
├── ScrapePartnersCommand.php      # Полный парсинг партнёров с сайта
├── UpdatePartnersCommand.php      # Обновление конкретных партнёров по ID
├── ImportPartnersCsvCommand.php   # Импорт из CSV в базу данных
Infrastructure/Scraper/
├── PartnerPageScraper.php         # HTTP-запросы к страницам Bitrix24
├── PartnerHtmlParser.php          # Парсинг HTML → структурированные данные
├── PartnerCsvStorage.php          # Чтение/запись CSV-файлов
├── ScrapeStateManager.php         # Управление state-файлами (resume)
```

## Команды

### `partners:scrape` — Полный парсинг партнёров

Парсит всех партнёров с сайта Bitrix24 и сохраняет в CSV-файл. Поддерживает resume при обрыве.

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--base-url` | URL страницы списка партнёров | `https://www.bitrix24.ru/partners/country__19/` |
| `--output-file` | Путь к выходному CSV файлу | `partners.csv` |
| `--page-delay` | Задержка между страницами (сек) | `2` |
| `--partner-delay` | Задержка между партнёрами (сек) | `2` |
| `--insecure` | Отключить проверку SSL | `false` |
| `--resume` | Продолжить с места обрыва | `false` |
| `--full-refresh` | Перезаписать существующий файл | `false` |

**Примеры:**

```bash
# Парсинг партнёров России (дефолт)
php bin/console partners:scrape

# Полный перезапись (если файл уже существует)
php bin/console partners:scrape --full-refresh

# Парсинг партнёров Казахстана
php bin/console partners:scrape --base-url=https://www.bitrix24.kz/partners/country__36/

# Кастомный выходной файл и ускоренный парсинг
php bin/console partners:scrape --output-file=partners_kz.csv --page-delay=1 --partner-delay=1

# Продолжить прерванный парсинг
php bin/console partners:scrape --resume

# Для dev-окружения (без SSL-проверки)
php bin/console partners:scrape --insecure
```

**Механизм resume:** При прерывании (Ctrl+C) создаётся state-файл `<output>.state.json` с информацией о последней обработанной странице. При запуске с `--resume` парсинг продолжится с этого места.

**Обнаружение бана:** Команда автоматически определяет блокировку по двум признакам:
- 10 пустых страниц подряд — немедленная остановка
- Более 50% пустых страниц — предупреждение после завершения

При бане рекомендуется увеличить `--page-delay` и `--partner-delay` до 2-3 секунд.

---

### `partners:update` — Скрейпинг конкретных партнёров по ID

Скрейпит детальные страницы указанных партнёров с сайта Bitrix24 и сохраняет результат в отдельный CSV-файл. Работает автономно — не требует предварительно существующего CSV. Выходной файл имеет тот же формат, что и при полной выгрузке (`partners:scrape`), и далее подаётся на вход команде `bitrix24:partners:import`.

**Константы:**

| Константа | Значение | Описание |
|-----------|----------|----------|
| `DEFAULT_BASE_DOMAIN` | `https://www.bitrix24.ru` | Домен Bitrix24 по умолчанию |
| `DEFAULT_INSECURE` | `false` | Проверка SSL по умолчанию |

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--partner-ids` | ID партнёров через запятую (обязательная) | — |
| `--base-domain` | Домен Bitrix24 для загрузки детальных страниц | `https://www.bitrix24.ru` |
| `--output-file` | Путь к выходному CSV файлу | `partners_update.csv` |
| `--partner-delay` | Задержка между партнёрами (сек) | `2` |
| `--insecure` | Отключить проверку SSL | `false` |

> **Важно:** Опция `--partner-ids` обязательна.

**Как определяется URL детальной страницы:** URL конструируется из `--base-domain` и ID партнёра: `{base-domain}/partners/partner/{id}/`. Например: `https://www.bitrix24.ru/partners/partner/3240/`.

**Примеры:**

```bash
# Скрейпить двух партнёров (Россия, дефолт)
php bin/console partners:update --partner-ids=3240,5859557

# Скрейпить одного партнёра без задержки
php bin/console partners:update --partner-ids=3240 --partner-delay=0

# Скрейпить партнёров Казахстана
php bin/console partners:update --partner-ids=3240 --base-domain=https://www.bitrix24.kz

# Кастомный выходной файл
php bin/console partners:update --partner-ids=3240,5859557 --output-file=partners_kz_update.csv

# Для dev-окружения (без SSL-проверки)
php bin/console partners:update --partner-ids=3240 --insecure
```

**Workflow обновления партнёров:**

```bash
# Шаг 1: Скрейпить указанных партнёров с сайта
php bin/console partners:update --partner-ids=3240,5859557 --output-file=partners_update.csv

# Шаг 2: Импортировать результат в БД
php bin/console bitrix24:partners:import partners_update.csv --strategy-update=replace
```

---

### `bitrix24:partners:import` — Импорт из CSV в БД

Импортирует партнёров из CSV-файла в базу данных через Doctrine ORM.

**Аргументы:**

| Аргумент | Описание |
|----------|----------|
| `file` | Путь к CSV файлу (обязательный) |

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--skip-errors` `-s` | Пропускать строки с ошибками | `false` |

**Примеры:**

```bash
# Импорт с остановкой на первой ошибке
php bin/console bitrix24:partners:import partners.csv

# Импорт с пропуском ошибочных строк
php bin/console bitrix24:partners:import partners.csv --skip-errors
```

**Обязательные колонки CSV:** `title`, `bitrix24_partner_number`

**Опциональные колонки:** `site`, `phone`, `email`, `open_line_id`, `external_id`, `logo_url`

---

## CSV-формат

Все команды работают с единым форматом CSV:

```
bitrix24_partner_number,title,site,phone,email,logo_url,detail_page_url,base_domain,scraped_at
3240,Hoster.KZ,https://b24.kz/,8-727-2-379-284,info@b24.kz,https://.../logo.jpg,/partners/partner/3240/,https://www.bitrix24.kz,2026-05-01T12:27:22+00:00
```

| Колонка | Описание |
|---------|----------|
| `bitrix24_partner_number` | Уникальный номер партнёра в Bitrix24 |
| `title` | Название компании |
| `site` | Сайт компании |
| `phone` | Телефон |
| `email` | Email |
| `logo_url` | URL логотипа |
| `detail_page_url` | Относительный путь до детальной страницы |
| `base_domain` | Домен для загрузки детальной страницы (https://www.bitrix24.ru, https://www.bitrix24.kz и т.д.) |
| `scraped_at` | Дата/время последнего скрейпинга (ISO 8601) |

---

## Запуск через Docker (Makefile)

```bash
# Парсинг партнёров Казахстана
make test-run-partners

# Обновление конкретных партнёров
make test-run-update-partners

# Импорт в БД
make test-run-partners-import
```

---

## Рекомендации

- **Один файл = один домен.** Не смешивайте партнёров с разных доменов (Россия, Казахстан) в одном CSV, если не планируете обновлять их отдельно.
- **Задержки.** Используйте `--page-delay=2` и `--partner-delay=2` (дефолт) для безопасного парсинга. Уменьшение задержек повышает риск бана.
- **Resume.** Если парсинг прервался — запускайте с `--resume`, не с `--full-refresh`.
- **Импорт в БД.** Перед импортом убедитесь, что схема БД создана (`make schema-create`).
