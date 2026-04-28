# Plan ScrapePartnersCommand

## Два сценария работы

1. Единоразовая выгрузка.
2. Обновление по одному партнеру, обновление по списку партнеров.

Дефолтные данные:
 - BASE_URL = 'https://www.bitrix24.kz/partners/country__22/';
 - FILTE_NAME = partners.csv 

Входные параметры при запуске команды: 
- BASE_URL
- FILE_NAME
- Флаг с тем что была ошибка и нужно продолжить с последнего партнера на котором оборвалось ? (Куда записывать страницу последнюю которую спарсиили)


Что нужно попробовать:

Надо юзать по дефолту https://github.com/php-http/discovery потому что выбор может быть
например газлайт или httpclient например.

Надо пробнуть может использовать https://github.com/thephpleague/csv .

## Ход работы скрипта
1. Определить для прогресс бара колличество страниц с партнерами. 
 Берем например 1 страницу проверяем есть ли в html что то. 
 Берем например 100 страницу проверяем есть ли в html что то.
 Если есть берем еще + 100 страницу то есть 200 и проверяем есть ли что то в html.
 Если нету берем 150 страницу то есть делим на 2. И стараемся вычислить последнюю страницу.

То есть в консоли сначало ожидание для определения колличество страниц.

2. Определили что колличество страниц которые нужно стянуть является 350 и на каждой странице по 16 партнеров = 5600
То есть в прогресс бар нужно отталикиваться от цифры 5600. это 100 процентов .

3. Начало парсинга
 3.1 Получаем 1 страницу с партнерами.
 3.2 Начинаем обработку партнеров по одному. 
   3.2.1 Получили ссылку на детальную страницу партнера
 3.3. Парсим данные которые нам нужны.
 3.4. Записываем полученные данные в json файл или csv например. Который по дефолту указан либо в параметрах.
 
## Ограничения и контекст

- Команда работает **только с CSV**, никакой БД.
- CSV файл хранится **в репозитории библиотеки** (закоммичен в git), обновляется периодически командой.
- ImportPartnersCsvCommand — вне scope, не трогаем.

---

## HTTP Client — php-http/discovery

Переходим с `Symfony\Contracts\HttpClient\HttpClientInterface` на HTTPlug + auto-discovery,
чтобы потребитель мог использовать Guzzle, Symfony, cURL или любую другую реализацию.

### Зависимости (добавить в composer.json)

```
"php-http/discovery": "^1.19",
"php-http/httplug": "^3.0",
"php-http/message": "^1.16"
```

### suggests (в composer.json)

```json
"suggest": {
    "php-http/guzzle7-adapter": "For Guzzle HTTP client",
    "symfony/http-client": "For Symfony HTTP client",
    "php-http/curl-client": "For cURL-based client"
}
```

### Использование

```php
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;

// В конструкторе — опциональная инъекция, fallback на discovery
public function __construct(
    ?HttpClient $httpClient = null
) {
    $this->httpClient = $httpClient ?? HttpClientDiscovery::find();
    $this->requestFactory = MessageFactoryDiscovery::find();
}
```

**Тонкость:** Symfony Console commands не всегда хорошо играют с optional constructor args через DI.
Возможно, придётся делать LocatorAwareInterface или отдельный фабричный метод.
Либо можно делать discovery лениво — в execute(), а не в конструкторе.

---

## CSV — league/csv

Уже подключён `league/csv: ^9.0`. Используем его вместо fputcsv.

```php
use League\Csv\Reader;
use League\Csv\Writer;

// Чтение существующего CSV (для update)
$reader = Reader::createFromPath($csvPath, 'r');
$reader->setHeaderOffset(0);

// Запись
$writer = Writer::createFromPath($csvPath, 'w+');
$writer->insertOne($headers);
$writer->insertAll($records);
```

**Важно:** league/csv не поддерживает in-place update. Стратегия для обновления:
1. Прочитать весь CSV в память (при ~5000 записей это тривиально)
2. Обновить нужные записи по bitrix24_partner_number как ключу
3. Перезаписать файл целиком

---

## Режимы работы команды

Одна команда `partners:scrape` с режимами через опции:

| Режим | Вызов | Описание |
|-------|-------|----------|
| Полная выгрузка | `partners:scrape` | Парсим все страницы с сайта → записываем новый CSV |
| Resume | `partners:scrape --resume` | Продолжить с места обрыва (из state-файла) |
| Полное обновление | `partners:scrape --full-refresh` | Перечитать всех с сайта → перезаписать существующий CSV |
| Точечное обновление | `partners:scrape --partner-ids=123,456` | Обновить конкретных партнёров (перезаписать их строки в CSV) |
| Обновление из файла | `partners:scrape --partner-ids-from-file=ids.csv` | Обновить партнёров из файла (одна колонка — номер партнёра) |

---

## Ответы на вопросы из плана

**Q1: Одна команда или две?**
→ Одна `partners:scrape` с опциями `--resume`, `--full-refresh`, `--partner-ids`, `--from-file`.

**Q2: Для обновления что используем?**
→ `bitrix24_partner_number` как ключ + `detail_page_url` из CSV для прямого доступа к детальной странице.

**Q2.1: Список партнёров?**
→ Через `--partner-ids=1,2,3` или `--from-file=ids.csv` (одна колонка — номера).

---

## Ход работы скрипта (обновлённый)

### Режим 1: Полная выгрузка (default / --full-refresh)

```
1. [Определение кол-ва страниц] — бинарный поиск
   ├── Проверяем страницу 1 → существует
   ├── Проверяем страницу 100 → существует?
   │   ├── Да → проверяем 200...
   │   └── Нет → бинарный поиск между 1 и 100
   └── Нашли N = последняя страница
       → Всего партнёров ≈ N × 16

2. [Инициализация ProgressBar] на N×16

3. [Цикл по страницам 1..N]
   ├── POST запрос с ajax=Y, page_n=$i
   ├── Парсим список партнёров (16 шт)
   └── Для каждого партнёра:
       ├── Парсим базовые данные из списка (name, number, phone)
       ├── GET детальная страница
       ├── Парсим детальные данные (email, logo, site)
       ├── Записываем в CSV (streaming — по мере обработки)
       ├── advance() прогресс-бар
       ├── sleep(DELAY)
       └── Обновляем state-файл

4. [Завершение]
   ├── Удаляем state-файл
   └── Вывод статистики
```

### Режим 2: Resume (--resume)

```
1. Читаем state-файл → last_page, last_partner_index
2. Продолжаем с шага 3 выше, начиная с сохранённой позиции
```

### Режим 3: Обновление конкретных (--partner-ids)

```
1. Читаем существующий CSV → массив по ключу bitrix24_partner_number
2. Для каждого ID из списка:
   ├── Берём detail_page_url из CSV (если есть)
   │   └── Если нет — парсим страницу списка, ищем по data-partner-id
   ├── GET детальная страница
   ├── Парсим данные
   ├── Обновляем данные в массиве
   └── sleep(DELAY)
3. Перезаписываем CSV из обновлённого массива
```

---

## State-файл для resume

Путь: `{output_file}.state.json` — рядом с CSV, при успешном завершении удаляется.

```json
{
  "mode": "full_scrape",
  "base_url": "https://www.bitrix24.kz/partners/country__22/",
  "total_pages": 350,
  "last_completed_page": 17,
  "last_partner_number_on_page": 3,
  "output_file": "partners_data.csv",
  "started_at": "2026-04-28T10:00:00+06:00",
  "updated_at": "2026-04-28T10:15:30+06:00"
}
```

---

## Структура CSV (предлагаемая)

```csv
bitrix24_partner_number,title,site,phone,email,logo_url,detail_page_url,open_line_id,external_id,scraped_at
```

Добавленные колонки относительно текущей реализации:
- `detail_page_url` — URL детальной страницы партнёра, нужен для обновления и отладки
- `scraped_at` — дата/время последнего скрейпа (ISO 8601), чтобы понимать свежесть данных

---

## Тонкости и подводные камни

### 1. Бинарный поиск — edge cases
- Страница 1 может не существовать (сайт недоступен) — нужно сразу падать с ошибкой
- На некоторых сайтах «несуществующая» страница возвращает не 404, а редирект на страницу 1
  или пустой JSON — проверяй и контент, и статус
- Битрикс может вернуть HTML с сообщением «партнёры не найдены» вместо пустого ответа

### 2. Дедупликация в CSV
При записи проверять: если bitrix24_partner_number уже есть — перезаписывать строку,
не добавлять дубль. Критично при resume, когда одна страница может быть обработана дважды.

### 3. CSV — streaming vs batch
- При полной выгрузке: писать по мере обработки (streaming) — не держать 5000+ записей в памяти
- При обновлении (--partner-ids): загрузить весь файл, обновить записи, записать целиком —
  это ок для ~5000 строк

### 4. Парсинг — DomCrawler вместо DOMDocument
Symfony DomCrawler уже есть в зависимостях, даёт CSS-селекторы:

```php
$crawler = new Crawler($html);
$partners = $crawler->filter('div.bp-partner-list-item-cnr.js-partners-list-item');

foreach ($partners as $partnerNode) {
    $nodeCrawler = new Crawler($partnerNode);
    $name = $nodeCrawler->filter('a')->first()->text();
    $url = $nodeCrawler->filter('a')->first()->attr('href');
    $number = (int) $nodeCrawler->attr('data-partner-id');
}
```

Чище, чем DOMDocument с @ suppression, и не нужно использовать DOMElement.

### 5. Для --partner-ids — как найти URL детальной страницы?
Два варианта:
- Вариант A: В CSV уже есть колонка detail_page_url — используем её напрямую (рекомендуется)
- Вариант B: Парсим страницу списка, ищем по data-partner-id, берём ссылку.
  Дорого для больших списков — лишние запросы к страницам списка.

Рекомендация: добавить detail_page_url в CSV (см. структуру выше).

### 6. Парсинг может сломаться
HTML-структура сайта может измениться:
- Оборачивать каждый extract* метод в try-catch
- При ошибке парсинга конкретного поля — записывать пустое значение и логировать warning
- Не падать целиком из-за одного партнёра (использовать --skip-errors подход)

### 7. SSL verify
Не отключать verify_peer/verify_host в проде. Сделать опцию --insecure для dev-окружения,
по умолчанию проверка включена.

### 8. Rate limiting
Сделать задержки конфигурируемыми через опции:
```
--page-delay=3 --partner-delay=2
```

---

## Проблемы в текущей реализации (ScrapePartnersCommand.php)

1. `$currentPage = 17` — захардкожен стартовый номер страницы (строка 73)
2. Нет `declare(strict_types=1)` — нарушает стандарты проекта
3. `AsCommand` закомментирован — команда не регистрируется через атрибуты
4. `verify_peer: false` — отключена проверка SSL (строка 108)
5. Нет прогресс-бара — при 5000+ партнёров непонятно, на каком этапе процесс
6. `OutputInterface` хранится как свойство — anti-pattern, лучше передавать через параметры методов
7. Не используется league/csv — хотя уже есть в зависимостях, используется fputcsv
8. Нет обработки retry — при ошибке на одной странице весь процесс падает
9. Symfony DomCrawler импортирован, но парсинг через DOMDocument с @ suppression