# План: Рефакторинг scraper-инфраструктуры

## Контекст

Перед доработкой команд `partners:update` и `bitrix24:partners:import` нужно реорганизовать scraper-инфраструктуру:
1. Спрятать HTML-парсинг внутрь скрейпера (инкапсуляция)
2. Изолировать консольный вывод от бизнес-логики (quiet mode для cron)

**Этот план выполняется ПЕРЕД** `plans/partner-update-command.md` и `plans/partner-import-upsert.md`.

---

## Задача 1: PartnerPageScraper + PartnerHtmlParser как зависимость

### 1.1 Создать DTO для данных партнёра

**Файл:** `src/Bitrix24Partners/Infrastructure/Scraper/PartnerData.php`

```php
readonly class PartnerData
{
    public function __construct(
        public int $bitrix24PartnerNumber,
        public string $title,
        public ?string $site,
        public ?string $phone,
        public ?string $email,
        public ?string $logoUrl,
        public string $detailPageUrl,
        public string $baseDomain,
        public CarbonImmutable $scrapedAt,
    ) {}
}
```

### 1.2 PartnerPageScraper получает PartnerHtmlParser как зависимость

**Файл:** `src/Bitrix24Partners/Infrastructure/Scraper/PartnerPageScraper.php`

- Добавить `PartnerHtmlParser` в конструктор
- Новый публичный метод:

```php
public function fetchPartnerData(int $partnerId, string $baseDomain, bool $insecure = false): ?PartnerData
```

Логика метода:
1. Конструировать URL: `{baseDomain}/partners/partner/{partnerId}/`
2. HTTP-запрос через `fetchPartnerDetailHtml()` → HTML
3. `$this->parser->parsePartnerDetailPage($html)` → данные
4. Собрать и вернуть `PartnerData` или `null` если не удалось загрузить

Старый метод `fetchPartnerDetailHtml()` — сделать приватным.

### 1.3 PartnerHtmlParser — без изменений

Файл остаётся как есть. Единственная ответственность — парсинг HTML.

### 1.4 Обновить потребителей

**Файлы:**
- `src/Bitrix24Partners/Console/ScrapePartnersCommand.php`
- `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

В обоих:
- Убрать `PartnerHtmlParser` из зависимостей (`__construct`)
- Использовать `PartnerPageScraper::fetchPartnerData()` вместо связки `fetchPartnerDetailHtml()` + `parsePartnerDetailPage()`
- Обновить DI-конфигурацию

---

## Задача 2: Изоляция консольного вывода

### Принцип

- Бизнес-логика (скрейпинг, запись CSV, парсинг) — не знает про консоль. Работает тихо, возвращает данные.
- Логирование через `LoggerInterface` — всегда, независимо от консоли.
- Консольный вывод (SymfonyStyle, ProgressBar, текст) — только в классах-командах (`*Command.php`).

### Уровни вывода

Использовать встроенные уровни verbosity Symfony Console:

| Уровень | Флаг | Что показывать |
|---------|------|----------------|
| Quiet | `-q` | Только ошибки (error) |
| Normal | (по умолчанию) | Заголовок + результат (success/error) + прогресс-бар |
| Verbose | `-v` | + note/warning сообщения |
| Very verbose | `-vv` | + детали по каждому партнёру |

### 2.1 Рефакторинг ScrapePartnersCommand

**Файл:** `src/Bitrix24Partners/Console/ScrapePartnersCommand.php`

- Прогресс-бар: только при `verbosity >= VERBOSITY_NORMAL`
- Детальные сообщения по партнёрам: только при `verbosity >= VERBOSITY_VERBOSE`
- Warning/notice: только при `verbosity >= VERBOSITY_VERBOSE`
- Заголовок и итог: при `verbosity >= VERBOSITY_NORMAL`
- Ошибки: всегда (даже в quiet)

### 2.2 Рефакторинг UpdatePartnersCommand

**Файл:** `src/Bitrix24Partners/Console/UpdatePartnersCommand.php`

- Прогресс-бар: только при `verbosity >= VERBOSITY_NORMAL`
- Сообщения по каждому партнёру: только при `verbosity >= VERBOSITY_VERBOSE`
- Итог: при `verbosity >= VERBOSITY_NORMAL`

### Паттерн проверки

```php
// Прогресс-бар
if ($output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
    $progressBar = new ProgressBar($output, $total);
    $progressBar->start();
}

// Детальные сообщения
if ($io->isVerbose()) {
    $io->text(sprintf('Партнёр #%d: OK', $partnerId));
}

// Ошибки — всегда
$io->error('...');
```

---

## Что НЕ меняется

- `PartnerHtmlParser` — остаётся как отдельный сервис (парсинг HTML)
- `PartnerCsvStorage` — остаётся как отдельный сервис
- `ScrapeStateManager` — остаётся как отдельный сервис
- CSV-формат — без изменений
- Сущности и UseCase'ы — без изменений

---

## Структура после рефакторинга

```
Infrastructure/Scraper/
├── PartnerPageScraper.php     # HTTP-запросы + делегирует парсинг в PartnerHtmlParser
├── PartnerHtmlParser.php      # Только парсинг HTML (без изменений)
├── PartnerData.php            # DTO — результат скрейпинга
├── PartnerCsvStorage.php      # Чтение/запись CSV (без изменений)
├── ScrapeStateManager.php     # State-файлы resume (без изменений)
```

```
Console/
├── ScrapePartnersCommand.php      # UI-слой + verbosity
├── UpdatePartnersCommand.php      # UI-слой + verbosity
├── ImportPartnersCsvCommand.php   # UI-слой (без изменений в этой задаче)
```

---

## Порядок выполнения

1. Задача 1.1 — Создать PartnerData DTO
2. Задача 1.2 — PartnerPageScraper получает PartnerHtmlParser как зависимость, новый метод fetchPartnerData()
3. Задача 1.3 — PartnerHtmlParser без изменений (пропускаем)
4. Задача 1.4 — Обновить потребителей (убрать PartnerHtmlParser из команд)
5. Задача 2.1 — Изоляция вывода ScrapePartnersCommand
6. Задача 2.2 — Изоляция вывода UpdatePartnersCommand
7. `make lint-phpstan`
