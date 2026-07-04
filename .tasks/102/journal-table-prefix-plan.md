## План по issue #102: префикс `b24lib_` для journal table

### Summary
Issue `#102` описывает несогласованность Doctrine mapping для `Bitrix24\Lib\Journal\Entity\JournalItem`: таблица `journal` не была переведена на namespaced-нейминг `b24lib_*`, хотя остальные entity tables уже были нормализованы в рамках issue `#93`.

Целевое состояние:
- таблица journal хранится в `b24lib_journal`;
- явно именованные индексы journal используют тот же префикс;
- документация и changelog не оставляют старые имена актуальными.

### Scope
- зафиксировать `table="b24lib_journal"` в `config/xml/Bitrix24.Lib.Journal.Entity.JournalItem.dcm.xml`;
- переименовать явно заданные journal indexes в `b24lib_journal_*`;
- обновить `src/Journal/Docs/README.md`, где таблица и индексы названы текстом;
- добавить upgrade note в `CHANGELOG.md` как breaking change для существующих установок, уже создавших таблицу `journal`.

### Out of Scope
- автоматические миграции внутри библиотеки;
- изменение доменной модели, PHP API и repository-контрактов;
- переименование колонок или структуры journal payload/context.

### Test Cases and Verification
1. `make test-unit`
- unit suite остаётся зелёным после изменения mapping-сопровождения и документации.

2. `make test-functional`
- Doctrine продолжает успешно поднимать схему с `b24lib_journal`;
- functional suite остаётся зелёным без ссылок на старые имена journal schema objects.

3. Документационная проверка
- `src/Journal/Docs/README.md` показывает актуальные имена `b24lib_journal` и `b24lib_journal_*`;
- `CHANGELOG.md` содержит явный upgrade path `journal -> b24lib_journal` и переименование индексов.

### Assumptions
- source of truth по схеме: XML mapping в `config/xml`;
- для issue `#102` принимается singular table name `b24lib_journal`, потому что именно это имя уже используется в текущем change set и оно согласовано с issue body как допустимый вариант;
- миграцию существующей БД выполняет потребитель библиотеки вручную перед обновлением.
