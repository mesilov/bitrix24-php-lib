## Makefile Parity Plan: `bitrix24-php-lib` in `b24phpsdk v3` Style

### Summary
Rebuild local `Makefile` in the structural and naming style of `b24phpsdk` `v3`:
- source style reference: https://github.com/bitrix24/b24phpsdk/blob/v3/Makefile
- target file: `Makefile`

Chosen decisions:
- Use only new target names (no backward-compat aliases).
- Keep only targets relevant to this repository (no copied integration matrix from SDK).

### Public Interface Changes (Make Targets)
Replace current target names with this final target set:

1. Core behavior and scaffolding
- `.DEFAULT_GOAL := help`
- `%: @: # silence`
- `help` with grouped sections (docker/composer/lint/tests/dev/db/debug)
- `ENV := $(PWD)/.env`, `ENV_LOCAL := $(PWD)/.env.local` (keep repo-local env model)

2. Docker targets
- `docker-init` (down -> build/pull as needed -> composer install -> up)
- `docker-up`
- `docker-down`
- `docker-down-clear`
- `docker-pull`
- `docker-restart` (depends on `docker-down docker-up`)

3. Composer targets
- `composer-install`
- `composer-update`
- `composer-dumpautoload`
- `composer-clear-cache` (from old `clear`)
- `composer` (pass-through arguments via `$(filter-out ...)`)

4. Lint/quality targets
- `lint-allowed-licenses`
- `lint-cs-fixer`
- `lint-cs-fixer-fix`
- `lint-phpstan`
- `lint-rector`
- `lint-rector-fix`
- `lint-all` (aggregator)

5. Test targets
- `test-unit` (old `test-run-unit`)
- `test-functional` (old `test-run-functional`, including doctrine schema reset steps)
- `test-functional-one` (old `run-one-functional-test`; keep same default filter/path unless changed later)

6. Utility/dev targets
- `php-cli-bash`
- `debug-show-env` (old `debug-print-env`)
- `doctrine-schema-drop` (old `schema-drop`)
- `doctrine-schema-create` (old `schema-create`)

7. Phony declarations
- Add `.PHONY` for each non-file target, matching `b24phpsdk` style.

### Implementation Details (Decision-Complete)
1. Rewrite file header and baseline structure to mirror `b24phpsdk` style:
- shebang placement, exported timeout vars, `.DEFAULT_GOAL`, wildcard silence, env includes, help block first.

2. Standardize all docker invocations to `docker compose` (space form), not `docker-compose`.

3. Replace old names entirely:
- remove `default`, `init`, `up`, `down`, `down-clear`, `restart`, `clear`, `test-run-unit`, `test-run-functional`, `run-one-functional-test`, `debug-print-env`, `schema-drop`, `schema-create`, `start-rector`, `coding-standards`.

4. Preserve command semantics for this repo:
- functional tests still run doctrine schema drop/create/update before phpunit.
- lint commands still use installed vendor binaries from current project.
- composer pass-through remains unchanged behavior-wise.

5. Ensure tab indentation for all recipe lines (fix current mixed-space recipe issue).

### Test Cases and Scenarios
Run after rewrite:

1. Structural/syntax checks
- `make help` prints grouped menu and exits 0.
- `make -n docker-up`, `make -n test-unit`, `make -n lint-all` produce expected command chains.

2. Target behavior smoke checks
- `make docker-up`
- `make composer-install`
- `make lint-cs-fixer`
- `make lint-phpstan`
- `make test-unit`
- `make test-functional` (with DB env loaded)

3. Pass-through checks
- `make composer "install --dry-run"`
- `make php-cli-bash`

4. Regression checks
- Confirm removed legacy target names now fail (expected), because compatibility aliases were explicitly not requested.

### Assumptions and Defaults
- Keep env file location at project root (`.env`, `.env.local`), not `tests/.env` from SDK.
- Keep only targets backed by tools/tests present in this repo (`phpunit` suites: `unit_tests`, `functional_tests`).
- Do not introduce SDK-specific dev/documentation/ngrok/integration-scope targets that have no local implementation.
- English target naming and help text preserved in SDK style.
