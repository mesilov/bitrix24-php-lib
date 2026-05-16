# План: Рефакторинг UpdatePartnersCommand

## Контекст

Команда `partners:update` раньше требовала предварительно существующий CSV-файл и читала из него `detail_page_url` и `base_domain`. Теперь работает автономно — получает ID партнёров, конструирует URL, скрейпит, пишет результат в отдельный CSV. Результат подаётся на вход команде `bitrix24:partners:import`.

Архитектурные паттерны: `UpdateConfig` DTO, 1 коллбек `onProgress` (по аналогии с `ScrapePartnersCommand`).

---

## Задача 1: Создать UpdateConfig ✅

**Файл:** `src/Bitrix24Partners/UseCase/Scrape/UpdateConfig.php`

```php
readonly class UpdateConfig
{
    public readonly string $baseDomain;

    public function __construct(
        public array $partnerIds,
        public string $outputFile,
        string $baseDomain = 'https://www.bitrix24.ru',
        public int $delay = 2,
        public bool $insecure = false,
    ) {
        $this->baseDomain = rtrim($baseDomain, '/');
    }
}
```

---

## Задача 2: Убрать --partner-ids-from-file ✅

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

- Удалена опция `--partner-ids-from-file` из `configure()`
- Удалён метод `executePartnerUpdateFromFile()`
- Убрана ветка `if ('' !== $partnerIdsFromFile)` из `execute()`
- Сообщение об ошибке: только `--partner-ids`

---

## Задача 3: Обновить configure() ✅

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

- Добавлена опция `--base-domain` (дефолт `https://www.bitrix24.ru`)
- Дефолт `--output-file` изменён с `partners.csv` на `partners_update.csv`

---

## Задача 4: Переписать execute() ✅

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

**Новая логика:**
1. Парсит CLI-опции → создаёт `UpdateConfig`
2. Создаёт 1 коллбек `onProgress(string $event, int $value)` с `match`:
   - `'partner_start'` → показать ID партнёра в ProgressBar
   - `'partner_advance'` → advance ProgressBar
3. Для каждого partner_id из `UpdateConfig`:
   a. Скрейпить детальную страницу через `PartnerPageScraper` (URL конструирует скрапер)
   b. Получить `PartnerData`
   c. Записать в CSV через `PartnerCsvStorage`
4. Вывести отчёт: сколько скрейпнуто, сколько ошибок

**Ключевые отличия от старой версии:**
- Не читает существующий CSV
- URL конструируется из `baseDomain` + ID (внутри `PartnerPageScraper`)
- Пишет результат в отдельный файл (дефолт `partners_update.csv`)
- Формат CSV идентичен формату полной выгрузки (partners:scrape)
- Прогресс через 1 коллбек, не инлайн

---

## Задача 5: Обновить bin/console ✅

Без изменений — конструктор `UpdatePartnersCommand` не поменялся.

---

## Что НЕ меняется

- `PartnerPageScraper` — без изменений
- `PartnerHtmlParser` — без изменений
- `PartnerCsvStorage` — без изменений
- `ScrapePartnersCommand` — без изменений
- `ImportPartnersCsvCommand` — без изменений
