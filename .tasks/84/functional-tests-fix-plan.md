## План устранения падения `make test-functional` (совместимость `ContactPerson` с SDK interface)

### Summary
Диагностический запуск `make test-functional` завершился до старта тестов с `Fatal error` при загрузке классов Doctrine/Entity:
- Команда упала на шаге `php bin/doctrine orm:schema-tool:drop --force`.
- Причина: несовместимая сигнатура метода в `ContactPerson` с контрактом SDK.

Подтверждённая ошибка:
- `ContactPerson::markEmailAsVerified(): void`
- требует соответствия `ContactPersonInterface::markEmailAsVerified(?CarbonImmutable $verifiedAt = null): void`

Файлы:
- `src/ContactPersons/Entity/ContactPerson.php:173`
- `vendor/bitrix24/b24phpsdk/src/Application/Contracts/ContactPersons/Entity/ContactPersonInterface.php:83`

### Important Interface Changes Needed
1. Привести сигнатуры методов сущности к актуальному SDK контракту:
- `markEmailAsVerified(?CarbonImmutable $verifiedAt = null): void`
- `markMobilePhoneAsVerified(?CarbonImmutable $verifiedAt = null): void`

2. Добавить отсутствующий метод интерфейса:
- `isPartner(): bool`

Дополнительно выявлено по статическому сравнению:
- В классе сейчас `markMobilePhoneAsVerified(): void` без параметра.
- В классе отсутствует `isPartner()`, хотя он обязателен в интерфейсе.

### Implementation Plan
1. Обновить `ContactPerson` сигнатуры обоих `mark*Verified` методов под интерфейс.
2. Внутри методов использовать переданный `$verifiedAt`, а при `null` ставить `new CarbonImmutable()`.
3. Добавить реализацию `isPartner(): bool` с семантикой контракта (true при наличии `bitrix24PartnerId`).
4. Проверить, что атрибуты `#[\Override]` остаются валидными после правок.
5. Перезапустить:
- `make test-functional`
- при успехе дополнительно `make test-unit` как регрессия по доменной модели.

### Test Cases and Scenarios
1. Инфраструктурный smoke:
- `php bin/doctrine orm:schema-tool:drop --force` больше не падает с `Fatal error`.

2. Основной сценарий:
- `make test-functional` проходит стадию bootstrap и выполняет тесты (или падает уже на реальных assertions, а не на загрузке класса).

3. Регрессия:
- `make test-unit` остаётся зелёным после изменения сигнатур и добавления `isPartner()`.

### Assumptions and Defaults
- Источник истины по контрактам: установленная версия `bitrix24/b24phpsdk` в `vendor`.
- Поведение `mark*Verified` должно поддерживать опциональный timestamp из интерфейса.
- `isPartner()` реализуется как проверка `null !== $this->bitrix24PartnerId`.
