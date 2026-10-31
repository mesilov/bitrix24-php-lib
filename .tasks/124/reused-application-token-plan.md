# Повторная установка с прежним application_token

Issue: https://github.com/mesilov/bitrix24-php-lib/issues/124. База: `dev`, `a2fdbb21000eeef2e9e1f3e533043cc5edb495f7`. Область и целевая логика согласованы владельцем в обсуждении; поручение — подготовить issue и PR, без слияния и обновления CRM2Spreadsheet.

## Цель и границы

Устранить ошибочное отождествление токена приложения и отдельной установки. Сохранить текущие статусы и OAuth-проверку. Удаление остаётся разрешённым только для `active`/`blocked`; новые статусы, callback nonce и привязка доставок к поколениям не добавляются.

## Контекст платформы

Официальные страницы: [callback установки](https://apidocs.bitrix24.ru/settings/app-installation/mass-market-apps/installation-callback.html), [проверка событий](https://apidocs.bitrix24.ru/api-reference/events/safe-event-handlers.html), [ONAPPUNINSTALL](https://apidocs.bitrix24.ru/api-reference/common/events/on-app-uninstall.html). application_token служит проверке подлинности и может повторяться при переустановке на том же портале; OAuth-авторизация новой установки проверяется отдельно. В uninstall нет OAuth credentials, CLEAN задаёт политику очистки; event_handler_id необязателен и не является ID доставки.

`.mcp.json` содержит ожидаемый bitrix24-dev URL и не изменялся при fetch. Инструменты этого MCP не предоставлены текущему клиенту; документация проверена через Context7 `/bitrix24/b24restdocs` и официальные страницы. GitHub MCP также недоступен; используется предусмотренный репозиторием fallback `gh`.

## Целевой контракт

1. `Flusher::withPortalLock()` не запрещает повторную установку по истории application_token. OAuth verification в Install/OnAppInstall и блокировки по member/installation сохраняются. Аргумент applicationToken остаётся принимаемым для совместимости вызовов; он не определяет поколение.
2. `Uninstallation` идентифицируется installationId. tokenHash/memberHash сохраняются как вспомогательные неуникальные данные проверки retry; исходные значения и старые receipts не теряются.
3. `UninstallationStore::begin()` сначала ищет соответствующую текущую active/blocked установку и проверяет её member ID. Исторический completed receipt не мешает новому удалению. Если соответствующей текущей установки нет, можно возобновить pending receipt с совпадающими token/member. Completed receipt не запускает повторную очистку.
4. `complete()` по-прежнему фиксирует CLEAN из доверенного receipt и атомарно завершает очистку конкретной установки. Повторный complete не исполняет cleaner второй раз.
5. При неразличимых старом и новом событиях с одинаковым токеном действует текущее состояние. Защита от запоздавшего события с тем же токеном после новой активации не обещается. При разных токенах изоляция старого retry и новой установки сохраняется.

## Файлы и реализация

- `src/Services/Flusher.php`: убрать проверку постоянного retirement токена; сохранить guards по installation ID и защите записей после удаления.
- `src/ApplicationInstallations/Infrastructure/Doctrine/UninstallationStore.php`: current-first поиск, pending retry по token/member вместо find по token как PK.
- `config/xml/Bitrix24.Lib.ApplicationInstallations.Entity.Uninstallation.dcm.xml`: PK installationId, вспомогательный индекс token/member.
- `src/ApplicationInstallations/Docs/uninstall-schema.sql`: новая схема; отдельный upgrade SQL для существующей таблицы #122 с сохранением данных.
- `tests/Functional/ApplicationInstallations/UseCase/Uninstall/HandlerTest.php`: заменить тесты ошибочного запрета положительными регрессиями; добавить полные циклы UI setup/ONAPPINSTALL/uninstall и проверки миграции.
- `src/ApplicationInstallations/Docs/uninstall.md`, `CHANGELOG.md`: обновить контракт повторов и последовательность миграции потребителей.

## Проверки

- [x] Зафиксировать failing regressions до production edits.
- [x] Tokenless UI setup + ONAPPINSTALL после завершённого удаления с тем же токеном; duplicate ONAPPINSTALL сохраняет UUID.
- [x] Прямая установка с прежним токеном после удаления.
- [x] Второе удаление с тем же токеном создаёт отдельный receipt; дальнейший duplicate — no-op.
- [x] Pending cleanup не затеняет текущую установку; wrong member/token и CLEAN retry проверяются существующими и дополнительными тестами.
- [x] Upgrade сохраняет существующий receipt и допускает второй с тем же токеном; свежая схема соответствует mapping.
- [x] `make lint-all`, затем `make test-unit`, затем `make test-functional`; изолированная PostgreSQL tmpfs без доступа к существующим БД.
- [x] Независимое ревью: существенных замечаний нет.
- [x] Опубликован PR #125 в `dev` по шаблону. Слияние остаётся владельцу.

## Результаты проверки

- До production edits: базовые lint/unit/functional прошли (242 unit, 112 functional); новые регрессии дали 2 ошибки InactiveInstallationException и 1 провал второго удаления с тем же токеном.
- После исправления: `make lint-all`, `make test-unit`, `make test-functional` выполнены последовательно и успешно: 242 unit / 501 assertions, 115 functional / 354 assertions.
- Для запуска использован отдельный временный Compose project с PostgreSQL tmpfs, без портов и persistent volumes. Исходная схема потребителя и его зависимости не изменялись.
- Rector применён только к обновлённому функциональному тесту через `make lint-rector-fix`; затем полный quality gate повторён.
- Независимое read-only ревью не выявило существенных ошибок. Дополнительная проверка ties среди нескольких pending receipts отмечена как необязательное дальнейшее усиление покрытия.

## Дополнение после обновления CI-образа

Публикация `php-cli` с PHP 8.4.27 сняла блокировку `roave/security-advisories`. Повторные CI запуски подтвердили unit/functional, PHPStan, CS Fixer и лицензии. Rector при свежем `composer update` требует `ExplicitNullableObjectInstanceRector` в 13 файлах, включая ранее существовавший код; локально использовался Rector 2.7.0. Для завершения проверок применяется только штатный `make lint-rector-fix`, без отключения правил и изменения бизнес-логики. Проверки повторяются на свежих зависимостях в отдельном временном vendor и новом CI-образе, без изменения зависимостей приложения-потребителя.

CI-версия Rector подтверждена логом: 2.8.3. Штатное автоисправление изменило 13 файлов только по правилу `ExplicitNullableObjectInstanceRector`; отдельный коммит сохранит видимость этой механической правки.

После `make lint-rector-fix` применён `make lint-cs-fixer-fix` для нормализации имён классов. Затем на PHP 8.4.27 со свежими зависимостями успешно выполнены `make lint-all`, `make test-unit` (242/501), `make test-functional` (115/354). Независимое ревью подтвердило эквивалентность всех nullable-проверок; ограничений версий и конфигурации правил не меняли.

Образ опубликован запуском https://github.com/mesilov/bitrix24-php-lib/actions/runs/41226084421. Метаданные amd64/arm64 содержат PHP 8.4.27; запуск контейнера подтвердил 8.4.27. Обновление приложения-потребителя и слияние PR не выполняются в этой задаче.
