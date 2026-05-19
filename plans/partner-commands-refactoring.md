# План доработки функционала команд парсинга партнёров

## Пункт 1: BanDetector

**Новый файл:** `src/Bitrix24Partners/Infrastructure/Scraper/BanDetector.php`

- Константы: `CONSECUTIVE_EMPTY_THRESHOLD = 10`, `EMPTY_RATIO_THRESHOLD = 0.5`
- Методы: `onEmptyPage(): bool`, `onSuccessfulPage(): void`, `isSuspicious(): bool`
- Интеграция в `ScrapeWorkflow::run()` — заменить inline-логику (строки 78-115, 145-153)
- Интеграция в `UpdateWorkflow::run()` — добавить проверку (сейчас отсутствует)

## Пункт 2: processPartner — создавать только при наличии детальной страницы

**Изменения в `ScrapeWorkflow::processPartner()` (строки 162-205):**
- `fetchPartnerData()` вернул `null` → не пишем в CSV, логируем warning
- Старый else-блок (строки 183-193) с неполным PartnerData — удалить

**Расширение `ScrapeResult`:**
- Добавить `skippedNoDetailPage: int` и `skippedPartnerNumbers: array<int>`
- Console command выводит: "Пропущено без детальной страницы: N (ID: 1, 2, 3...)"
- Логировать через LoggerInterface

## Пункт 3: Bitrix24Zone enum

**Новый файл:** `src/Bitrix24Partners/ValueObjects/Bitrix24Zone.php`

```php
enum Bitrix24Zone: string
{
    case RU = 'ru';
    case KZ = 'kz';

    public function getBaseDomain(): string
    {
        return match($this) {
            self::RU => 'https://www.bitrix24.ru',
            self::KZ => 'https://www.bitrix24.kz',
        };
    }
}
```

**Миграция:**

| Файл | Было | Стало |
|------|------|-------|
| `ScrapeConfig` | `baseUrl`, вычисляемый `baseDomain` | `zone` (Bitrix24Zone), `baseDomain` через `zone->getBaseDomain()` |
| `UpdateConfig` | `baseDomain: string` | `zone` (Bitrix24Zone), `baseDomain` через `zone->getBaseDomain()` |
| `PartnerData` | `baseDomain: string` | `zone: string` |
| `PartnerCsvStorage` | колонка `base_domain` | колонка `zone` |
| `PartnerPageScraper` | принимает `baseDomain` | принимает `Bitrix24Zone`, сам получает `baseDomain` |
| `ScrapePartnersCommand` | `--base-url` | `--zone=ru` (дефолт) |
| `UpdatePartnersCommand` | `--base-domain` | `--zone=ru` (дефолт) |
| `ImportWorkflow::buildUpsertCommand()` | читает `base_domain` | читает `zone` |
| CSV формат | `base_domain` колонка | `zone` колонка |

## Пункт 4: Обновить документацию

- `docs/commands/partner-commands.md` — заменить примеры с `--base-url`/`--base-domain` на `--zone`
- `docs/dev/partner-import-scenarios.md` — обновить CSV-формат

## Порядок реализации

1. BanDetector — isolated, нет зависимостей
2. processPartner + ScrapeResult — пересекается с п.1 (ScrapeWorkflow)
3. Bitrix24Zone enum — isolated, больше файлов затрагивает
4. Документация — после всех изменений
