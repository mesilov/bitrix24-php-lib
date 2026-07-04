## План по issue #93: нормализация Doctrine-таблиц (`b24lib_` + plural)

### Summary
Нужно унифицировать имена всех Doctrine-таблиц библиотеки и явно именованных schema-объектов, чтобы они:
- имели префикс `b24lib_`
- использовали множественное число для таблиц сущностей

Целевое переименование:
- `application_installation` -> `b24lib_application_installations`
- `application_settings` -> `b24lib_application_settings`
- `bitrix24account` -> `b24lib_bitrix24_accounts`
- `contact_person` -> `b24lib_contact_persons`

Работа ограничивается обновлением маппингов, тестов и документации внутри библиотеки. Встроенная миграция существующих БД в самой библиотеке в scope не входит, но upgrade path должен быть явно описан в `CHANGELOG.md` как breaking change.

### Important Interface Changes
Публичные PHP API, сигнатуры классов и repository-интерфейсы не меняются.

Меняются только инфраструктурные имена Doctrine schema-объектов:
- XML mapping `table="..."`
- имена `indexes`, `unique-constraints` и других явно именованных DB-объектов в mappings
- ожидания в functional/tests и schema assertions
- документация и changelog, где старые таблицы названы явно

### Implementation Changes
1. Обновить Doctrine XML mappings в `config/xml`, заменив имена таблиц на новые `b24lib_*`.
2. Не менять имена колонок, foreign key columns, embedded mappings и доменные имена сущностей.
3. Переименовать все явно заданные schema-объекты в mappings так, чтобы они были консистентны с новой схемой и использовали `b24lib_`-нейминг:
- `unique_app_setting_scope` -> `b24lib_application_settings_unique_scope`
- `idx_application_installation_id` -> `b24lib_application_settings_idx_application_installation_id`
- `idx_b24_user_id` -> `b24lib_application_settings_idx_b24_user_id`
- `idx_b24_department_id` -> `b24lib_application_settings_idx_b24_department_id`
- `idx_key` -> `b24lib_application_settings_idx_key`
- `idx_status` -> `b24lib_application_settings_idx_status`
- если в затронутых mappings обнаружатся другие явно именованные schema-объекты, их привести к тому же правилу: `b24lib_<table>_<object-purpose>`
4. Не добавлять migration runner, console command или встроенный SQL helper: breaking change сопровождается документированным ручным апгрейдом через `CHANGELOG.md`, а не автоматическим апгрейдом внутри библиотеки.
5. Обновить тесты, которые опираются на фактическую схему Doctrine/PostgreSQL:
- проверки через `schema-tool:create` / `schema-tool:update --dump-sql`
- любые assertions или fixture-логика, где фигурируют старые table names
- при наличии явных schema assertions добавить проверку новых имен таблиц и явно именованных индексов/constraints
- smoke на introspection/truncate, чтобы работа со схемой не зависела от старых имен
6. Обновить документацию:
- `CHANGELOG.md` в секции `Unreleased 0.5.0` добавить отдельный `BC`/`Breaking Changes` блок с upgrade note для существующих установок
- в upgrade note перечислить все переименования `old -> new` для таблиц и явно именованных schema-объектов и явно указать, что перед обновлением потребитель должен переименовать существующие объекты схемы вручную
- `src/ApplicationSettings/Docs/application-settings.md` и другие места, где таблицы названы текстом
7. Не трогать строки исключений и доменные сообщения вида `bitrix24account not found`, если они описывают сущность, а не SQL-таблицу.

### Test Cases and Scenarios
1. `make test-functional`
- схема успешно дропается и создается через Doctrine с новыми table names
- `orm:schema-tool:update --dump-sql` не предлагает лишних изменений после `create`
- functional test suite остается зеленым

2. `make test-unit`
- unit suite остается зеленым как регрессия после изменения mapping metadata и docs-сопровождения

3. Ручная schema-проверка в рамках functional bootstrap
- в БД присутствуют только новые таблицы `b24lib_*`
- явно именованные индексы и unique constraints используют новый `b24lib_*`-нейминг
- старые имена не появляются в generated schema

4. Документационная проверка
- в `CHANGELOG.md` есть отдельный `BC`/upgrade note с полным списком переименований таблиц и явно именованных schema-объектов
- в пользовательской документации не осталось старых таблиц как актуальных

### Assumptions and Defaults
- Источник истины по схеме: Doctrine XML mappings в `config/xml`.
- Scope изменения: меняются table names и явно именованные schema-объекты; column names и семантика связей не переименовываются.
- Breaking change допустим для milestone `0.5.0` и должен быть явно отмечен в changelog.
- Для существующих установок библиотека не поставляет автоматический migration path; потребитель выполняет ручное переименование таблиц и других затронутых schema-объектов по инструкции из `CHANGELOG.md`.
