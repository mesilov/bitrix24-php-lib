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

### `partners:update` — Обновление конкретных партнёров

Обновляет данные (телефон, email, сайт, логотип) для указанных партнёров, перечитывая их детальные страницы с сайта. Работает с существующим CSV-файлом.

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--partner-ids` | ID партнёров через запятую | — |
| `--partner-ids-from-file` | Файл с ID партнёров для обновления | — |
| `--output-file` | Путь к CSV файлу | `partners.csv` |
| `--partner-delay` | Задержка между партнёрами (сек) | `2` |
| `--insecure` | Отключить проверку SSL | `false` |

> **Важно:** Одна из опций `--partner-ids` или `--partner-ids-from-file` обязательна.

**Примеры:**

```bash
# Обновить двух партнёров
php bin/console partners:update --partner-ids=3240,5859557

# Обновить одного партнёра без задержки
php bin/console partners:update --partner-ids=3240 --partner-delay=0

# Обновить из файла с ID
php bin/console partners:update --partner-ids-from-file=partner_ids.csv

# С кастомным CSV-файлом
php bin/console partners:update --partner-ids-from-file=ids.csv --output-file=partners_kz.csv

# С отключенной SSL-проверкой
php bin/console partners:update --partner-ids=3240 --insecure
```

**Формат файла с ID:** Обычный CSV, где первая колонка содержит числовой ID партнёра:
```
3240
5859557
15549800
```

**Как определяется домен:** Домен для загрузки детальной страницы берётся из колонки `base_domain` в CSV. Поэтому каждый партнёр всегда обновляется с правильного домена ( Россия, Казахстан и т.д.).

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
