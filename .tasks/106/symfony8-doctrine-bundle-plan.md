## План по issue #106

### Summary

Issue `#106` требует убрать Symfony 8 boot blocker в consumer applications,
обновив root constraint `doctrine/doctrine-bundle` с `3.2.2` до линии `3.3`.

Проблема уже подтверждена вне библиотеки: с текущим constraint consumer app на
Symfony `8.0.*` падает на bootstrap, а с временным override до
`doctrine/doctrine-bundle:^3.3` успешно проходит `composer update`,
`php bin/console cache:clear` и HTTP boot.

### Scope

- обновить dependency constraint в `composer.json` до installable варианта для текущего состояния Packagist;
- зафиксировать Symfony 8 compatibility в `CHANGELOG.md`;
- прогнать repository-local verification только через `Makefile`;
- проверить, что change set не требует дополнительных library-local code changes.

### Non-goals

- не перепроектировать интеграцию Doctrine или Symfony в runtime-коде библиотеки;
- не добавлять ad hoc test commands вне `Makefile`;
- не эмулировать внешний consumer app внутри этого репозитория.

### Acceptance Mapping

1. `composer.json` требует `doctrine/doctrine-bundle:^3.2.2 || ^3.3@dev`, так как на момент работы стабильный `3.3` ещё отсутствует на Packagist, а чистый `^3.3` неразрешим при `minimum-stability: stable`.
2. CHANGELOG явно фиксирует, что следующий релиз снимает Symfony 8 boot blocker.
3. Library-local quality gate не показывает регрессии на актуальной среде проекта.

### Verification

- `make test-unit`
- при доступной среде: `make test-functional`
- при необходимости: `make lint-all`
