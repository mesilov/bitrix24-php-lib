# Консольные команды для работы с партнёрами Bitrix24

## Обзор

Архитектура разделена на следующие компоненты:

```
Console/
├── ScrapePartnersCommand.php      # Полный парсинг партнёров с сайта
├── UpdatePartnersCommand.php      # Обновление конкретных партнёров по ID
├── ImportPartnersCsvCommand.php   # Импорт из CSV в базу данных
UseCase/Scrape/
├── ScrapeConfig.php               # DTO конфигурации парсинга
├── ScrapeResult.php               # DTO результата парсинга
├── ScrapeWorkflow.php             # Оркестрация полного парсинга
├── UpdateConfig.php               # DTO конфигурации обновления
├── UpdateWorkflow.php             # Оркестрация обновления по ID
├── PartnerData.php                # DTO данных партнёра (9 полей)
UseCase/Import/
├── ImportConfig.php               # DTO конфигурации импорта
├── ImportResult.php               # DTO результата импорта
├── ImportWorkflow.php             # Оркестрация импорта CSV → БД
UseCase/Upsert/
├── Command.php                    # Команда создания/обновления
├── Handler.php                    # Обработчик Upsert
UseCase/Delete/
├── Command.php                    # Команда удаления
├── Handler.php                    # Обработчик мягкого удаления
Infrastructure/Scraper/
├── PartnerPageScraper.php         # HTTP-запросы к страницам Bitrix24
├── PartnerHtmlParser.php          # Парсинг HTML → структурированные данные
├── PartnerCsvStorage.php          # Чтение/запись CSV-файлов
├── ScrapeStateManager.php         # Управление state-файлами (resume)
```

## Общие принципы скрейпинга

Команды `partners:scrape` и `partners:update` работают с сайтом Bitrix24 и подчиняются одним правилам:

- **Задержки.** Используйте `--page-delay=2` и `--partner-delay=2` (дефолт). Уменьшение задержек повышает риск бана.
- **Обнаружение бана.** Команды автоматически определяют блокировку по пустым страницам. Рекомендуется увеличить задержки до 2-3 секунд.
- **SSL.** Для dev-окружения используйте `--insecure`.
- **Один файл = один домен.** Не смешивайте партнёров с разных доменов (Россия, Казахстан) в одном CSV.

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

**Механизм resume:** При обрыве (сетевая ошибка, бан, таймаут и т.д.) создаётся state-файл `<output>.state.json` с информацией о последней обработанной странице. При запуске с `--resume` парсинг продолжится с этого места.

**Рекомендации:**

- Если парсинг прервался — запускайте с `--resume`, не с `--full-refresh`.

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

# Шаг 2: Импортировать результат в БД (partial — не трогает остальных партнёров)
php bin/console bitrix24:partners:import partners_update.csv --sync-mode=partial
```

**Рекомендации:**

- Скрейпит только указанных партнёров, остальных не трогает.
- Результат — CSV-файл для дальнейшего импорта.
- При импорте результата всегда используйте `--sync-mode=partial`, иначе `--sync-mode=full` (дефолт) удалит всех партнёров, которых нет в этом файле.

---

### `bitrix24:partners:import` — Импорт из CSV в БД

Импортирует партнёров из CSV-файла в базу данных через Doctrine ORM. CSV — источник истины. Команда создаёт новых партнёров, обновляет существующих с изменившимися данными, а при полной синхронизации — помечает как удалённые отсутствующих в CSV.

**Аргументы:**

| Аргумент | Описание |
|----------|----------|
| `file` | Путь к CSV файлу (обязательный) |

**Опции:**

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--sync-mode` | `full` — полная синхронизация с soft-delete, `partial` — обновить только из файла | `full` |
| `--dry-run` | Показать что произойдёт без реальных изменений | `false` |
| `--skip-errors` `-s` | Пропускать строки с ошибками | `false` |

**Примеры:**

```bash
# Полная синхронизация (дефолт) — создать, обновить, удалить отсутствующих
php bin/console bitrix24:partners:import partners.csv

# Частичное обновление — только создать/обновить из файла, остальное не трогать
php bin/console bitrix24:partners:import partners_update.csv --sync-mode=partial

# Проверка без изменений
php bin/console bitrix24:partners:import partners.csv --dry-run

# Импорт с пропуском ошибочных строк
php bin/console bitrix24:partners:import partners.csv --skip-errors
```

**CSV-формат фиксирован** — файлы генерируются командами `partners:scrape` и `partners:update`.

**Рекомендации:**

- `--sync-mode=full` (дефолт) — CSV = полная выгрузка, отсутствующие партнёры будут помечены как удалённые.
- `--sync-mode=partial` — CSV = патч, только создать/обновить, остальное не трогается.
- Перед импортом убедитесь, что схема БД создана (`make schema-create`).
- Используйте `--dry-run` для предварительной проверки.

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
