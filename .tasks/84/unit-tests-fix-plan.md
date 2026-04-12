## План исправления падений `make test-unit` (18 ошибок Serializer/ObjectNormalizer)

### Summary
По результату запуска `make test-unit`:
- Всего: `97` тестов, `145` assertions
- Ошибки: `18`
- Все 18 ошибок однотипны и приходят из `SettingsFetcherTest` с `LogicException`:
  `ObjectNormalizer requires symfony/property-access`.

Источник падений:
- `tests/Unit/ApplicationSettings/Services/SettingsFetcherTest.php`
- Инициализация `ObjectNormalizer()` в `setUp()`.

Выбранная стратегия: добавить `symfony/property-access` в `require-dev`.

### Important Changes (Public Interfaces / Dependencies)
1. Обновить dev-зависимости проекта:
- `composer.json`: добавить `symfony/property-access` в `require-dev` (версия в линии Symfony 7, например `^7`).
- `composer.lock`: обновить lock-файл после установки зависимости.

2. Код бизнес-логики не менять:
- `src/ApplicationSettings/Services/SettingsFetcher.php` остаётся без изменений.
- Поведение API `SettingsFetcher::getItem()` и `SettingsFetcher::getValue()` не меняется.

### Implementation Steps
1. Добавить пакет:
- `docker compose run --rm php-cli composer require --dev symfony/property-access:^7`

2. Проверить, что dependency корректно зафиксирована:
- Убедиться, что в `composer.json` и `composer.lock` добавлен `symfony/property-access`.

3. Перезапустить юнит-тесты:
- `make test-unit`

4. Если останутся новые ошибки после этого фикса:
- Разобрать их как отдельную волну (ожидается, что текущие 18 ошибок исчезнут полностью).

### Test Cases and Scenarios
1. Основной сценарий:
- `make test-unit` должен завершиться с `exit code 0`.

2. Точечная проверка проблемного класса:
- Запустить только `SettingsFetcherTest` и убедиться, что тесты `getItem`/`getValue` больше не падают на `LogicException`.

3. Регрессия:
- Повторный запуск полного `make test-unit` для проверки, что добавление зависимости не вызвало побочных падений в остальных unit-тестах.

### Assumptions and Defaults
- Используемая версия Symfony в проекте остаётся в линии `^7`, поэтому `symfony/property-access:^7` совместим.
- Проблема инфраструктурная (отсутствующая dev-зависимость), а не дефект алгоритма `SettingsFetcher`.
- В рамках этого фикса не меняем структуру тестов и не переписываем сериализацию в `SettingsFetcherTest`.
