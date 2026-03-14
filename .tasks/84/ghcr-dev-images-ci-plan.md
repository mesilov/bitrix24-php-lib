## План внедрения GHCR-образов для dev/CI (`php-cli`)

### Summary
Цель: чтобы dev-образ `php-cli` собирался в CI и публиковался в GitHub Container Registry, а CI-тесты использовали pull этого образа из GHCR вместо локальной установки PHP.

Опорный референс из `bitrix24/b24phpsdk` (ветка `v3`):
- Workflow сборки: https://raw.githubusercontent.com/bitrix24/b24phpsdk/v3/.github/workflows/docker-build.yml
- `docker-compose` с `image + build`: https://raw.githubusercontent.com/bitrix24/b24phpsdk/v3/docker-compose.yaml

Выбранные решения:
- Теги: `:php-cli` + immutable `:php-cli-<short-sha>`.
- Триггер сборки: изменения `docker/php-cli/Dockerfile` + `workflow_dispatch`.
- CI потребление: unit/functional тесты запускаются в GHCR image.

### Important Changes / Interfaces
1. Новый CI workflow публикации образа
- Файл: `.github/workflows/docker-build.yml`
- Права job: `packages: write`, `contents: read`
- Buildx multi-arch: `linux/amd64,linux/arm64`
- Публикация тегов:
  - `ghcr.io/mesilov/bitrix24-php-lib:php-cli`
  - `ghcr.io/mesilov/bitrix24-php-lib:php-cli-<short-sha>`
- Кэш: `cache-from/to: type=gha`

2. Контракт образа для compose/dev
- Файл: `docker-compose.yaml`
- `php-cli` получает:
  - `image: ${PHP_CLI_IMAGE:-ghcr.io/mesilov/bitrix24-php-lib:php-cli}`
  - `build: { context: ./docker/php-cli }` (fallback для локальной пересборки)
- Поведение:
  - `make docker-pull` подтягивает GHCR image
  - `make docker-up --build` при необходимости пересобирает локально

3. Перевод тестовых workflow на GHCR image
- Файлы:
  - `.github/workflows/tests-unit.yml`
  - `.github/workflows/tests-functional.yml`
- Убрать `shivammathur/setup-php` (образ уже содержит PHP/extensions/composer)
- Добавить job-level container:
  - `container.image: ghcr.io/mesilov/bitrix24-php-lib:php-cli`
  - `container.credentials` через `${{ github.actor }}` + `${{ secrets.GITHUB_TOKEN }}`
- Добавить `permissions: packages: read` в тестовых job.

4. Корректировка functional env под container+services
- Сейчас `DATABASE_HOST=localhost`; в container job это неверно.
- Изменить на hostname service-контейнера (например `bitrix24-php-lib-test-database`), чтобы подключение к Postgres было стабильным.
- Шаг установки `postgresql-client`/`pg_isready` убрать (или оставить только если реально нужен CLI-инструмент в job).

### Implementation Steps (Decision Complete)
1. Создать `.github/workflows/docker-build.yml` по шаблону `b24phpsdk`, адаптировав:
- image path на `ghcr.io/mesilov/bitrix24-php-lib`
- два тега (`php-cli`, `php-cli-${short_sha}`)
- события: `push.paths: docker/php-cli/Dockerfile`, `workflow_dispatch`.

2. Обновить `docker-compose.yaml`:
- добавить `image` для `php-cli` с env-override
- сохранить `build.context` для fallback
- не менять `database` сервис.

3. Обновить `tests-unit.yml`:
- `permissions: packages: read`
- добавить `container.image` + `container.credentials`
- удалить setup-php step
- оставить `composer update` + `phpunit` как есть.

4. Обновить `tests-functional.yml`:
- `permissions: packages: read`
- добавить `container.image` + credentials
- сменить `DATABASE_HOST` на service name
- удалить setup-php step и apt/pg_isready шаги
- оставить schema-tool + phpunit шаги.

5. Проверить Makefile/локальный DX:
- Убедиться, что `docker-pull` реально тянет GHCR образ.
- При необходимости добавить короткую подсказку в `help` про переменную `PHP_CLI_IMAGE`.

### Test Cases and Scenarios
1. Публикация образа
- Изменить `docker/php-cli/Dockerfile` в ветке.
- Проверить, что `docker-build` workflow публикует оба тега в GHCR.

2. Pull в CI
- `tests-unit` и `tests-functional` стартуют в container image из GHCR без шага setup-php.
- Workflow не падают на pull/auth.

3. Functional DB connectivity
- `DATABASE_HOST` резолвится на service container.
- schema-tool команды проходят стабильно.

4. Локальный dev
- `make docker-pull` подтягивает `ghcr.io/mesilov/bitrix24-php-lib:php-cli`.
- `make docker-up` и `make test-*` остаются рабочими.

### Assumptions and Defaults
- GHCR package для репозитория доступен для чтения в Actions через `GITHUB_TOKEN`.
- Основной registry-путь фиксируем как `ghcr.io/mesilov/bitrix24-php-lib`.
- Для локальной разработки build fallback сохраняется (`build.context`) и не ломает текущий поток.
