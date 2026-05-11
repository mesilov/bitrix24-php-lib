# План: Рефакторинг UpdatePartnersCommand

## Контекст

Команда `partners:update` сейчас требует предварительно существующий CSV-файл и читает из него `detail_page_url` и `base_domain`. Нужно переделать чтобы команда работала автономно — получает ID партнёров, конструирует URL, скрейпит, пишет результат в отдельный CSV. Результат подаётся на вход команде `bitrix24:partners:import`.

Документация: `docs/commands/partner-commands.md`

---

## Задача 1: Добавить константы

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

Добавить в класс:
```php
private const DEFAULT_BASE_DOMAIN = 'https://www.bitrix24.ru';
private const DEFAULT_INSECURE = false;
```

---

## Задача 2: Убрать --partner-ids-from-file

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

- Удалить опцию `--partner-ids-from-file` из `configure()`
- Удалить метод `executePartnerUpdateFromFile()`
- Убрать ветку `if ('' !== $partnerIdsFromFile)` из `execute()`
- Обновить сообщение об ошибке: только `--partner-ids` обязательна

---

## Задача 3: Добавить --base-domain

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

Добавить опцию:
```php
->addOption('base-domain', null, InputOption::VALUE_REQUIRED, 'Домен Bitrix24', self::DEFAULT_BASE_DOMAIN)
```

---

## Задача 4: Обновить --insecure на дефолт из константы

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

Опция `--insecure` получает дефолт из константы `DEFAULT_INSECURE`. Через флаг переопределяется.

---

## Задача 5: Переписать логику executePartnerUpdate

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

**Текущая логика:**
1. Читает существующий CSV → map по partner_id
2. Ищет партнёра в CSV по ID
3. Берёт detail_page_url и base_domain из CSV
4. Скрейпит детальную страницу
5. Обновляет запись в map
6. Перезаписывает весь CSV

**Новая логика:**
1. Получить partner_ids из `--partner-ids`
2. Получить base_domain из `--base-domain` (дефолт из константы)
3. Для каждого partner_id:
   a. Конструировать URL: `{base_domain}/partners/partner/{partner_id}/`
   b. Скрейпить детальную страницу через `PartnerPageScraper`
   c. Парсить HTML через `PartnerHtmlParser`
   d. Собрать запись для CSV: bitrix24_partner_number, title, site, phone, email, logo_url, detail_page_url, base_domain, scraped_at
4. Записать все записи в выходной CSV через `PartnerCsvStorage`
5. Вывести отчёт: сколько скрейпнуто, сколько ошибок

**Ключевые отличия:**
- Не читает существующий CSV
- Конструирует URL из base_domain + ID
- Пишет результат в **отдельный** файл (дефолт `partners_update.csv`)
- Формат CSV идентичен формату полной выгрузки (partners:scrape)

---

## Задача 6: Обновить дефолт --output-file

Изменить дефолт с `partners.csv` на `partners_update.csv` чтобы не перезаписать случайно файл полной выгрузки.

---

## Что НЕ меняется

- `PartnerPageScraper` — без изменений
- `PartnerHtmlParser` — без изменений
- `PartnerCsvStorage` — без изменений (используется для записи)
- `ScrapePartnersCommand` — без изменений
- `ImportPartnersCsvCommand` — без изменений (пока, доработка в отдельном плане)

---

## Порядок выполнения

1. Задача 1 (константы)
2. Задача 2 (убрать --partner-ids-from-file)
3. Задача 3 (добавить --base-domain)
4. Задача 4 (обновить --insecure)
5. Задача 6 (дефолт output-file)
6. Задача 5 (переписать логику)
7. `make lint-phpstan`
