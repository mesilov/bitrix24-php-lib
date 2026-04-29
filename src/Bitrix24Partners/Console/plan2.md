# Plan ScrapePartnersCommandV2

## Цель
Написать с нуля новую команду `partners:scrape-v2`, старую `ScrapePartnersCommand` не трогаем — она как референс.

## Scope: полная выгрузка (режимы обновления — позже)

---

### Шаг 1: Зависимости
- `composer require symfony/dom-crawler`
- `composer require php-http/discovery php-http/httplug php-http/message`
- Добавить `suggest` в composer.json для адаптеров (guzzle, curl, symfony)

### Шаг 2: Создать новый файл `ScrapePartnersCommandV2.php`
- `declare(strict_types=1)`
- `AsCommand(name: 'partners:scrape-v2')`
- Конструктор с опциональным `HttpClient` через discovery (лениво, в `execute()`)
- Все CLI-опции: `--base-url`, `--output-file`, `--page-delay`, `--partner-delay`, `--insecure`, `--resume`, `--full-refresh`, `--partner-ids`, `--partner-ids-from-file`
- Пока скелет команды с `configure()` + пустой `execute()`

### Шаг 3: Бинарный поиск последней страницы
- Метод `findLastPage()` — 1 → 100 → 200, затем бинарный поиск
- Проверка: есть ли партнёры в HTML ответе (через DomCrawler)

### Шаг 4: Парсинг списка партнёров через DomCrawler
- Метод `parsePartnerListPage(string $html): array`
- CSS-селекторы вместо DOMDocument (подсматриваем селекторы из старой команды)
- Возвращает массив: `[{number, name, detail_page_url, phone}, ...]`

### Шаг 5: Парсинг детальной страницы партнёра через DomCrawler
- Метод `fetchAndParsePartnerDetail(string $detailUrl): array`
- Возвращает: `{email, logo_url, site}`
- try-catch вокруг каждого поля — не падать на одном партнёре

### Шаг 6: Прогресс-бар
- После `findLastPage()` создаём `ProgressBar` на `lastPage × 16`
- `advance()` после каждого обработанного партнёра

### Шаг 7: CSV через league/csv (streaming)
- Метод `initCsvWriter()` — создаёт файл с заголовками
- Заголовки: `bitrix24_partner_number,title,site,phone,email,logo_url,detail_page_url,open_line_id,external_id,scraped_at`
- Streaming запись — по одному партнёру, не копим в памяти

### Шаг 8: Главный цикл (режим полной выгрузки)
- Цикл по страницам 1..N
- Для каждого партнёра: парсинг списка → детальная страница → запись в CSV → `advance()` → `sleep()` → обновление state-файла
- Дедупликация по `bitrix24_partner_number`

### Шаг 9: State-файл для `--resume`
- `{output_file}.state.json` — `last_completed_page`, `last_partner_index`
- Пишется после каждого партнёра
- Удаляется при успешном завершении

---

## Позже (не в этом цикле)
### Шаг 10: Режимы обновления (`--partner-ids`, `--partner-ids-from-file`, `--full-refresh`)
- Читаем CSV → обновляем нужные строки → перезаписываем целиком
