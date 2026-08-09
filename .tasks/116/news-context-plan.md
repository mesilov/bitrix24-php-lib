## План по issue #116

### Summary

Issue `#116` требует добавить новый bounded context `News` — простой агрегат контента
(новости приложения: `title`, `text`, `imageUrl`, `status`), который админ добавляет в админке и
который показывается внутри приложения. Контекст реализуем целиком внутри библиотеки,
без контракта в SDK, по образцу `Bitrix24Partners` / `Bitrix24Accounts`.

Доставка в колокольчик (`im.notify.system.add`) и чаты (`im.message.add`) — вне зоны
ответственности библиотеки.

### Scope

- `src/News/Entity/News.php` — aggregate root (extends `Bitrix24\Lib\AggregateRoot`);
- `src/News/Entity/NewsStatus.php` — enum `draft | published | deleted`;
- доменные события: `NewsCreatedEvent`, `NewsPublishedEvent`,
  `NewsRevertedToDraftEvent`, `NewsTitleChangedEvent`, `NewsTextChangedEvent`,
  `NewsImageChangedEvent`, `NewsDeletedEvent`;
- `src/News/Infrastructure/NewsRepositoryInterface.php` (контракт) + `src/News/Infrastructure/Doctrine/NewsRepository.php` (реализация);
- Doctrine XML-маппинг `config/xml/Bitrix24.Lib.News.Entity.News.dcm.xml`,
  таблица `b24lib_news`, поле `status` — `enum-type="string"`, поле `image_url` — string (nullable), таймстампы — `carbon_immutable`;
- CQRS UseCases: `Create`, `Update`, `Publish`, `RevertToDraft`, `Delete`; queries через `findPublished`/`findDrafts` в репозитории;
- Unit-тесты `tests/Unit/News/` (guards, переходы статусов, события);
- Functional-тесты `tests/Functional/News/` (CRUD репозитория на реальной БД);
- `CHANGELOG.md` — запись под 0.8.0;
- `src/News/Docs/` — зафиксировать, что доставка в bell/chat вне scope.

### Non-goals

- не добавлять контракт `Bitrix24\SDK\Application\Contracts\News` (всё локально в либе);
- не привязывать новость к `bitrix24AccountId` / `contactPersonId`;
- не реализовывать доставку в колокольчик/чат (зона приложения);
- не вводить новый folder-паттерн — следовать существующей структуре bounded context.

### Acceptance Mapping

1. `News` aggregate root: поля `id, title, text, imageUrl, status, createdAt, updatedAt`; `createdAt` readonly; пустые `title`/`text` отклоняются; `imageUrl` — ?string, URL only (либа не хранит файлы).
2. `NewsStatus` enum с guard'ами переходов: `draft→published` (`publish`), `published→draft` (`revertToDraft`), `draft|published→deleted` (`markAsDeleted`); недопустимые переходы кидают `LogicException`/`InvalidArgumentException`.
3. События генерируются через `AggregateRoot::events[]` на каждое действие (стиль `Bitrix24Partner`).
4. Маппинг + таблица `b24lib_news`; `make schema-create` создаёт таблицу без ошибок.
5. Репозиторий: `save`, `getById` (исключает `deleted`), `findByTitle` (исключает `deleted`), `findPublished` (пагинированный список), `findDrafts` (пагинированный список).
6. UseCases покрыты (команды + хендлеры); query-side через `findPublished`/`findDrafts` в репозитории.
7. `make test-unit` и `make test-functional` зелёные; `make lint-all` зелёный.
8. CHANGELOG обновлён под 0.8.0.

### Verification

- `make test-unit`
- `make test-functional`
- `make lint-all`
