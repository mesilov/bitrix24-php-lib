## План исправления преждевременного перехода install-flow в `active`

### Summary
Issue `#90` описывает баг в `ApplicationInstallations\UseCase\Install\Handler`: сейчас handler всегда вызывает `applicationInstalled($command->applicationToken)` и для `Bitrix24Account`, и для `ApplicationInstallation`, даже если `applicationToken === null`.

Из-за этого UI install-flow отрабатывает неверно:
- старт установки без `application_token` сразу переводит аккаунт и установку в `active`;
- доменные события завершения установки диспатчатся слишком рано;
- backend считает install-flow завершённым до прихода отдельного webhook / finish-step с `application_token`.

Проверка текущего кода подтвердила проблему:
- `src/ApplicationInstallations/UseCase/Install/Handler.php` безусловно вызывает `applicationInstalled(...)` для обеих сущностей;
- `Bitrix24Account::applicationInstalled(null)` и `ApplicationInstallation::applicationInstalled(null)` всё равно переводят сущности в `active` и создают finish-events;
- текущие functional tests в `tests/Functional/ApplicationInstallations/UseCase/Install/HandlerTest.php` закрепляют именно это преждевременное поведение.

### Key Design Constraint
Нельзя ограничиться только условием `if (null !== $command->applicationToken)` в `Install\Handler`.

Причина:
- `src/ApplicationInstallations/UseCase/OnAppInstall/Handler.php` сейчас ищет master account только в статусе `active`;
- после исправления issue `#90` аккаунт в UI flow должен оставаться в статусе `new`;
- если не скорректировать finish-flow, `OnAppInstall\Handler` перестанет находить аккаунт и установка сломается уже на следующем шаге.

Дополнительный контекст:
- `src/Bitrix24Accounts/UseCase/InstallFinish/Handler.php` уже работает со статусом `new` и переводит аккаунт в `active`;
- `OnAppInstall\Handler` сейчас только меняет `applicationStatus` и записывает `applicationToken`, но не завершает установку доменно.

### Important Changes / Interfaces
1. Исправить стартовый install-flow в `src/ApplicationInstallations/UseCase/Install/Handler.php`
- если `applicationToken !== null`, сохранить текущее поведение immediate install-finish;
- если `applicationToken === null`, создать `Bitrix24Account` и `ApplicationInstallation` в статусе `new`;
- не вызывать `applicationInstalled()`;
- не диспатчить `ApplicationInstallationFinishedEvent` и `Bitrix24AccountApplicationInstalledEvent`.

2. Привести finish-flow к консистентному сценарию
- определить, какой use case считается canonical finish-step для UI flow:
  - либо расширить `ApplicationInstallations\UseCase\OnAppInstall\Handler`, чтобы он завершал установку и для account, и для installation;
  - либо оставить `OnAppInstall` только для записи токена/статуса установки, но тогда явно связать его с `Bitrix24Accounts\UseCase\InstallFinish\Handler`.
- в любом варианте finish-step должен корректно работать с сущностями в статусе `new`.

3. Пересмотреть выборку аккаунта в `src/ApplicationInstallations/UseCase/OnAppInstall/Handler.php`
- убрать жёсткую зависимость от `Bitrix24AccountStatus::active`, если этот handler должен участвовать в finish-flow после старта без токена;
- либо искать master account в `new`, либо разрешить оба статуса (`new` / `active`) в зависимости от финального сценария.

4. Актуализировать functional tests
- переписать ожидания в `tests/Functional/ApplicationInstallations/UseCase/Install/HandlerTest.php` для сценария без токена;
- добавить или адаптировать тесты под двушаговый flow:
  - старт установки без токена сохраняет `new`;
  - finish-step / webhook с токеном переводит сущности в `active`;
  - события завершения диспатчатся только на finish-step.

### Implementation Plan
1. Зафиксировать целевую модель install-flow в тестах:
- `Install\Handler` с токеном => `active`;
- `Install\Handler` без токена => `new`;
- finish-step после получения `application_token` => `active`.

2. Изменить `src/ApplicationInstallations/UseCase/Install/Handler.php`:
- обернуть вызовы `applicationInstalled()` условием на `null !== $command->applicationToken`;
- убедиться, что сохранение новых сущностей без токена не порождает finish-events.

3. Привести следующий шаг install-flow к работе с новыми сущностями:
- обновить `src/ApplicationInstallations/UseCase/OnAppInstall/Handler.php` и/или `src/Bitrix24Accounts/UseCase/InstallFinish/Handler.php`;
- проверить, где именно должен происходить переход `new -> active` для `ApplicationInstallation`;
- исключить сценарий, при котором токен только записывается, а статус установки навсегда остаётся `new`.

4. Обновить functional coverage:
- `tests/Functional/ApplicationInstallations/UseCase/Install/HandlerTest.php`;
- `tests/Functional/ApplicationInstallations/UseCase/OnAppInstall/HandlerTest.php`;
- при необходимости `tests/Functional/Bitrix24Accounts/UseCase/InstallFinish/HandlerTest.php`.

5. Прогнать регрессию по install-flow и смежным сценариям.

### Test Cases and Scenarios
1. Прямой install с токеном:
- `Install\Handler` создаёт account и installation;
- обе сущности получают статус `active`;
- токен сохраняется;
- диспатчатся finish-events.

2. UI install-start без токена:
- `Install\Handler` создаёт account и installation;
- обе сущности остаются в статусе `new`;
- токен не сохраняется;
- finish-events не диспатчатся.

3. Finish-step после получения `application_token`:
- ранее созданные `new` account и installation находятся по `memberId`;
- токен сохраняется;
- обе сущности переходят в `active`;
- диспатчатся `Bitrix24AccountApplicationInstalledEvent` и `ApplicationInstallationFinishedEvent`.

4. Регрессия на reinstall / repeated install:
- сценарии с существующей активной установкой по тому же `memberId` продолжают корректно деактивировать старые сущности и создавать новый install-flow;
- поведение не должно зависеть от того, был ли токен на первом шаге.

### Files to Touch
- `src/ApplicationInstallations/UseCase/Install/Handler.php`
- `src/ApplicationInstallations/UseCase/OnAppInstall/Handler.php`
- возможно `src/Bitrix24Accounts/UseCase/InstallFinish/Handler.php`
- `tests/Functional/ApplicationInstallations/UseCase/Install/HandlerTest.php`
- `tests/Functional/ApplicationInstallations/UseCase/OnAppInstall/HandlerTest.php`
- возможно `tests/Functional/Bitrix24Accounts/UseCase/InstallFinish/HandlerTest.php`

### Definition of Done
Исправление считается завершённым, когда:
- старт установки без `application_token` оставляет `Bitrix24Account` и `ApplicationInstallation` в статусе `new`;
- токен и переход в `active` происходят только на отдельном finish-step;
- functional tests отражают двушаговый install-flow и проходят без закрепления преждевременного `active`.
