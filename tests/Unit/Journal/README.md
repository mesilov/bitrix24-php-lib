# Journal Unit Tests

Юнит-тесты для компонентов модуля Journal.

## Структура тестов

### Entity Tests
- **JournalItemTest.php** - тесты для сущности JournalItem
  - Проверка создания через PSR-3 фабричные методы (emergency, alert, critical, error, warning, notice, info, debug)
  - Валидация обязательных полей
  - Проверка контекста и payload

- **LogLevelTest.php** - тесты для enum LogLevel
  - Конвертация из PSR-3 строковых уровней
  - Case-insensitive обработка
  - Валидация всех 8 уровней логирования

### Infrastructure Tests
- **InMemoryJournalItemRepositoryTest.php** - тесты для in-memory репозитория
  - CRUD операции (save, findById, delete)
  - Фильтрация по installation ID и уровню логирования
  - Пагинация (limit, offset)
  - Подсчет записей
  - Удаление по дате

### Services Tests
- **JournalLoggerTest.php** - тесты для PSR-3 логгера
  - Проверка всех PSR-3 методов (info, error, warning, debug и т.д.)
  - Запись контекста (label, payload, userId, IP)
  - Интеграция с репозиторием
  - Валидация уровней логирования

## Запуск тестов

### Все юнит-тесты Journal модуля:
```bash
vendor/bin/phpunit --testsuite unit_tests --filter Journal
```

### Конкретный тест-класс:
```bash
vendor/bin/phpunit tests/Unit/Journal/Entity/JournalItemTest.php
vendor/bin/phpunit tests/Unit/Journal/Services/JournalLoggerTest.php
vendor/bin/phpunit tests/Unit/Journal/Infrastructure/InMemoryJournalItemRepositoryTest.php
vendor/bin/phpunit tests/Unit/Journal/Entity/LogLevelTest.php
```

### Все unit-тесты проекта:
```bash
vendor/bin/phpunit --testsuite unit_tests
```

### С покрытием кода:
```bash
vendor/bin/phpunit --testsuite unit_tests --filter Journal --coverage-html coverage/
```

## Покрытие

Тесты покрывают:
- ✅ Все PSR-3 уровни логирования
- ✅ Фабричные методы создания JournalItem
- ✅ Валидацию входных данных
- ✅ CRUD операции репозитория
- ✅ Фильтрацию и пагинацию
- ✅ Работу с контекстом и payload
- ✅ Конвертацию PSR-3 уровней

## Зависимости для тестов

```json
{
  "require-dev": {
    "phpunit/phpunit": "^11"
  }
}
```

Перед запуском тестов выполните:
```bash
composer install
```
