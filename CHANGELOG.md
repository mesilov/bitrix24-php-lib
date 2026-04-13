## 0.5.1

### Changed

- **Dependency refresh for PHP 8.5 and current QA toolchain**
    - Raised root PHP constraint from `8.3.* || 8.4.*` to `8.4.* || 8.5.*`
    - Allowed `giggsey/libphonenumber-for-php` `^9` in addition to `^8`
    - Updated dev tooling to current major versions: `phpstan` `^2`, `phpunit` `^13`, `psalm` `^6`, `rector` `^2`
    - Expanded Symfony dev constraints to support both `^7` and `^8` for `debug-bundle`, `property-access`, `stopwatch`, and `var-exporter`
- **Static-analysis compatibility cleanups**
    - Narrowed install/account handler internals with explicit assertions and intersection types for aggregate roots that emit domain events
    - Added explicit callback parameter types in `ApplicationSettingsListCommand`
    - Removed deprecated `strictBooleans` prepared set from `rector.php`

### Fixed

- **Functional test bootstrap compatibility with Doctrine ORM 3 on PHP 8.4+**
    - Enabled Doctrine native lazy objects in test `EntityManager` configuration
    - Restored successful `make test-functional` runs with current Symfony `var-exporter`
- **PHPUnit 13 test-suite compatibility**
    - Reworked unit and functional tests to stop using no-expectation mocks where stubs/fakes are more appropriate
    - Removed PHPUnit notices from `make test-unit` and `make test-functional`
- **ApplicationSettings repository functional coverage**
    - Replaced the previously skipped PostgreSQL unique-constraint test with an assertion of the actual database behavior for duplicate global settings with `NULL` scope values

## 0.5.0

### Added

- **Journal bounded context (main feature of 0.5.0)** — [#72](https://github.com/mesilov/bitrix24-php-lib/issues/72)
    - Added `JournalItem` aggregate and `Context` value object for portal technical logs
    - Added `Bitrix24\Lib\Journal\Entity\LogLevel` enum with PSR-3 compatible levels
    - Added `JournalItemRepositoryInterface`, `DoctrineDbalJournalItemRepository`, and `JournalLogger`
    - Added pagination-aware journal queries by `memberId` and `applicationInstallationId`
- **Install-flow documentation**
    - Added `src/ApplicationInstallations/Docs/application-installations.md` with one-step / two-step install contracts, canonical finish-step rules, and corner cases

### Changed

- **Application installation flow** — [#90](https://github.com/mesilov/bitrix24-php-lib/issues/90)
    - `Install` now distinguishes one-step installs with `applicationToken` from UI/two-step installs without token
    - `OnAppInstall` is now the canonical finish-step for pending installations created without a token
    - Duplicate `ONAPPINSTALL` events for already active installations are handled as warning `no-op` calls
- **Domain value object namespace**
    - `Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain` moved to `Bitrix24\Lib\Common\ValueObjects\Domain`
    - Updated install-related commands and tests to use the shared namespace
- **Developer workflow docs**
    - Added project-level MCP configuration in `.mcp.json`
    - Documented MCP checks and mandatory `Makefile` entrypoints for tests and linters in `README.md` and `AGENTS.md`

### BC

- **Doctrine schema naming normalization** — [#93](https://github.com/mesilov/bitrix24-php-lib/issues/93)
    - Tables renamed:
        - `application_installation` -> `b24lib_application_installations`
        - `application_settings` -> `b24lib_application_settings`
        - `bitrix24account` -> `b24lib_bitrix24_accounts`
        - `contact_person` -> `b24lib_contact_persons`
    - Explicit schema object names renamed for `b24lib_application_settings`:
        - `unique_app_setting_scope` -> `b24lib_application_settings_unique_scope`
        - `idx_application_installation_id` -> `b24lib_application_settings_idx_application_installation_id`
        - `idx_b24_user_id` -> `b24lib_application_settings_idx_b24_user_id`
        - `idx_b24_department_id` -> `b24lib_application_settings_idx_b24_department_id`
        - `idx_key` -> `b24lib_application_settings_idx_key`
        - `idx_status` -> `b24lib_application_settings_idx_status`
    - Existing PostgreSQL installations must rename the existing tables and explicitly named indexes before the first run on `0.5.0`
    - Example SQL:
```sql
ALTER TABLE application_installation RENAME TO b24lib_application_installations;
ALTER TABLE application_settings RENAME TO b24lib_application_settings;
ALTER TABLE bitrix24account RENAME TO b24lib_bitrix24_accounts;
ALTER TABLE contact_person RENAME TO b24lib_contact_persons;

ALTER INDEX unique_app_setting_scope RENAME TO b24lib_application_settings_unique_scope;
ALTER INDEX idx_application_installation_id RENAME TO b24lib_application_settings_idx_application_installation_id;
ALTER INDEX idx_b24_user_id RENAME TO b24lib_application_settings_idx_b24_user_id;
ALTER INDEX idx_b24_department_id RENAME TO b24lib_application_settings_idx_b24_department_id;
ALTER INDEX idx_key RENAME TO b24lib_application_settings_idx_key;
ALTER INDEX idx_status RENAME TO b24lib_application_settings_idx_status;
```

### Fixed

- **Premature activation during install** — [#90](https://github.com/mesilov/bitrix24-php-lib/issues/90)
    - `Bitrix24Account` and `ApplicationInstallation` no longer switch to `active` when `Install` is called without `applicationToken`
    - Finish events are no longer emitted before Bitrix24 sends the token-bearing finish step
- **Reinstall handling**
    - Reinstall over pending installations now blocks and archives the previous installation pair before creating a new one

## 0.4.0

### Added

- **ContactPersons support (main feature of 0.4.0)**
    - Added `ApplicationInstallations\UseCase\InstallContactPerson\Command` / `Handler` to create and link a `ContactPerson` to an `ApplicationInstallation`
    - Added `ApplicationInstallations\UseCase\UnlinkContactPerson\Command` / `Handler` to unlink a contact person from an installation
    - Added `ContactPersons\UseCase\ChangeProfile\Command` / `Handler` to update `FullName`, email, and mobile phone
    - Added `ContactPersons\UseCase\MarkEmailAsVerified\Command` / `Handler` to confirm email ownership
    - Added `ContactPersons\UseCase\MarkMobilePhoneAsVerified\Command` / `Handler` to confirm mobile phone ownership
- **`ContactPersonType` enum** (`personal` | `partner`) in `Bitrix24\Lib\ContactPersons\Enum`

### Changed

- **`ContactPerson` entity**
    - Constructor accepts optional `$createdAt` / `$updatedAt` parameters so SDK contract tests can assert stable timestamps
    - `$isEmailVerified` and `$isMobilePhoneVerified` are initialized from `$emailVerifiedAt` / `$mobilePhoneVerifiedAt` in constructor
    - `getBitrix24UserId()` return type narrowed from `?int` to `int` to match `ContactPersonInterface`
    - `markAsDeleted()` now throws `InvalidArgumentException` (was `LogicException`) to satisfy the SDK contract
- **`ApplicationInstallation` entity**
    - `unlinkContactPerson()` and `unlinkBitrix24PartnerContactPerson()` now return early when the respective ID is already `null` to avoid unnecessary `updatedAt` mutation
- **`OnAppInstall\Handler`**
    - Now throws `ApplicationInstallationNotFoundException` when installation cannot be found by member ID (instead of silent no-op)

### Fixed

- **SDK contract compatibility after `bitrix24/b24phpsdk` update**
    - Updated `createContactPersonImplementation()` signatures in `ContactPersonTest` and `ContactPersonRepositoryTest` (`int $bitrix24UserId` moved to position 5 and made non-nullable)
    - Narrowed `ContactPersonBuilder::$bitrix24UserId` from `?int` to `int`
    - Restored green unit test suite (`170` tests)

## 0.3.1

### Changed
 
- **Makefile aligned with b24phpsdk v3 style**
    - Set `help` as default target and added grouped help output
    - Switched Docker commands from `docker-compose` to `docker compose`
    - Renamed targets to SDK-style naming (`docker-*`, `test-unit`, `test-functional`, `debug-show-env`, `doctrine-schema-*`)
    - Added explicit `.PHONY` declarations for operational targets
    - Added `lint-all` aggregate target
- **Dependency update for PHP 8.4 compatibility**
    - Updated `darsyn/ip` from `^5` to `^6`
    - Removed runtime deprecation warnings from functional test runs
- **CI pipelines moved to dev Docker image from GHCR**
    - Added workflow to build and publish `php-cli` image to `ghcr.io/mesilov/bitrix24-php-lib` (`php-cli` and `php-cli-<sha>` tags)
    - Switched lint, unit, functional, and license-check workflows to run inside `ghcr.io/mesilov/bitrix24-php-lib:php-cli`
    - Added GitHub Actions package permissions for pulling private GHCR images in jobs
- **Docker Compose image source updated for dev workflow**
    - Added `image: ${PHP_CLI_IMAGE:-ghcr.io/mesilov/bitrix24-php-lib:php-cli}` to `php-cli` service
    - Kept local `build` section as fallback when registry tag is unavailable

### Fixed

- **Unit tests failing in `SettingsFetcherTest` due to missing serializer dependency**
    - Added `symfony/property-access` to `require-dev`
    - Restored successful run of `make test-unit` (`97 tests, 190 assertions`)
- **Functional tests bootstrap failure due to SDK contract mismatch**
    - Updated `ContactPerson::markEmailAsVerified()` and `ContactPerson::markMobilePhoneAsVerified()` signatures to match `ContactPersonInterface`
    - Added missing `ContactPerson::isPartner()` method implementation
    - Restored successful run of `make test-functional` (`62 tests, 127 assertions, 1 skipped`)

## 0.3.0

### Added

- **ApplicationSettings bounded context** for application configuration management — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
    - Full CRUD functionality with CQRS pattern (Create, Update, Delete use cases)
    - Multi-scope support: Global, Departmental, and Personal settings with cascading resolution
    - **SettingsFetcher service** with automatic deserialization support
        - Cascading resolution logic (Personal → Departmental → Global)
        - JSON deserialization to objects using Symfony Serializer
        - Comprehensive logging with LoggerInterface
    - **DefaultSettingsInstaller service** for bulk creation of default settings
    - Soft-delete support with `ApplicationSettingStatus` enum (Active/Deleted)
    - Event system with `ApplicationSettingsItemChangedEvent` for change tracking
    - CLI command `app:settings:list` for viewing settings with scope filtering
    - InMemory repository implementation for fast unit testing
    - Unique constraint on (installation_id, key, user_id, department_id)
    - Tracking fields: `changedByBitrix24UserId`, `isRequired`
- Database schema updates
    - Table `application_settings` with UUID v7 IDs
    - Scope fields: `b24_user_id`, `b24_department_id`
    - Status field with index for query optimization
    - Timestamp tracking: `created_at_utc`, `updated_at_utc`
- Comprehensive test coverage
    - Unit tests for entity validation and business logic
    - Functional tests for repository operations and use case handlers
    - Tests for all scope types and soft-delete behavior

### Changed

- **Refactored ApplicationSettings entity naming**
    - Renamed `ApplicationSetting` → `ApplicationSettingsItem`
    - Renamed all interfaces and events accordingly
    - Updated table name from `application_setting` → `application_settings`
- **Renamed service class for clarity** — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
    - Renamed `InstallSettings` → `DefaultSettingsInstaller` for better semantic clarity
    - Updated all references in documentation and tests
    - Updated log message prefixes to use new class name
- **Separated Create/Update use cases**
    - Create UseCase now only creates new settings (throws exception if exists)
    - Update UseCase for modifying existing settings (throws exception if not found)
    - Update automatically emits `ApplicationSettingsItemChangedEvent`
- **Simplified repository API**
    - Removed 6 redundant methods, kept only `findAllForInstallation()`
    - Renamed `findAll()` → `findAllForInstallationByKey()` to avoid conflicts
    - All find methods now filter by `status=Active` by default
    - Added optimized `findAllForInstallationByKey()` method
- **Enhanced SettingsFetcher**
    - Renamed `getSetting()` → `getItem()`
    - Renamed `getSettingValue()` → `getValue()`
    - Added automatic deserialization with type-safe generics
    - Non-nullable return types with exception throwing
- **ApplicationSettingsItem improvements**
    - UUID v7 generation moved inside entity constructor
    - Key validation: only lowercase latin letters and dots
    - Scope methods: `isGlobal()`, `isPersonal()`, `isDepartmental()`
    - `updateValue()` method emits change events
- **Makefile improvements**
    - Updated to use Docker for `composer-license-checker`
    - Aligns with other linting and analysis workflows
- **Code quality improvements**
    - Applied Rector automatic refactoring (arrow functions, type hints, naming)
    - Added `#[\Override]` attributes to overridden methods
    - Applied PHP-CS-Fixer formatting consistently
    - Added symfony/property-access dependency for ObjectNormalizer
- **Documentation improvements**
    - Translated ApplicationSettings documentation to English
    - Updated all code examples to reflect current codebase
    - Updated exception references to use SDK standard exceptions
    - Improved best practices and security sections
- **Test infrastructure improvements**
    - Created contract tests for ApplicationSettingsItemRepositoryInterface
    - Moved ApplicationSettingsItemInMemoryRepository from src to tests/Helpers
    - Added contract test implementations for both InMemory and Doctrine repositories
    - Refactored existing repository tests to focus on implementation-specific behavior

### Fixed

- **PHPStan level 5 errors related to SDK interface compatibility** — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
    - Removed invalid `#[\Override]` attributes from extension methods in `ApplicationInstallationRepository`
    - Fixed `findByMemberId()` call with incorrect parameter count in `OnAppInstall\Handler`
    - Added `@phpstan-ignore-next-line` comments for methods not yet available in SDK interface
    - Added TODO comments to track SDK interface extension requirements
- **Doctrine XML mapping**
    - Fixed `enumType` → `enum-type` syntax for Doctrine ORM 3 compatibility
- **Repository method naming conflicts**
    - Renamed methods to avoid conflicts with EntityRepository base class
- **Exception handling standardization** — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
    - Replaced custom exceptions with SDK standard exceptions for consistency
    - Removed `SettingsItemAlreadyExistsException` → using `Bitrix24\SDK\Core\Exceptions\InvalidArgumentException`
    - Removed `SettingsItemNotFoundException` → using `Bitrix24\SDK\Core\Exceptions\ItemNotFoundException`
    - Created `BaseException` class in `src/Exceptions/` for future custom exceptions
    - Updated all tests to expect correct SDK exception types
    - Fixed PHPDoc annotations to reference correct exception types
- **Type safety improvement in OnAppInstall Command** — [#64](https://github.com/mesilov/bitrix24-php-lib/issues/64)
    - Changed `$applicationStatus` parameter type from `string` to `ApplicationStatus` object
    - Improved type safety by enforcing proper value object usage
    - Removed unnecessary string validation in Command constructor
    - Eliminated redundant ApplicationStatus instantiation in Handler
    - Updated all related tests to use ApplicationStatus objects

### Removed

- **Get UseCase** - replaced with `SettingsFetcher` service (UseCases now only for data modification)
- **Redundant repository methods**
    - `findGlobalByKey()`, `findPersonalByKey()`, `findDepartmentalByKey()`
    - `findAllGlobal()`, `findAllPersonal()`, `findAllDepartmental()`
    - `deleteByApplicationInstallationId()`
    - `softDeleteByApplicationInstallationId()`
- **Hard delete from Delete UseCase** - replaced with soft-delete pattern
- **Entity getStatus() method** - use `isActive()` instead for better encapsulation
- **Static getRecommendedDefaults()** - developers should define their own defaults
- **Custom exception classes** — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
    - `ApplicationSettings\Services\Exception\SettingsItemNotFoundException`
    - `ApplicationSettings\UseCase\Create\Exception\SettingsItemAlreadyExistsException`

## 0.2.0

### Changed

Updated application contracts
fix minor errors

## 0.1.1

### Added

- Change php version requirements — [#44](https://github.com/mesilov/bitrix24-php-lib/pull/44)

## 0.1.0

### By [@mesilov](https://github.com/mesilov)

- Add initial project setup with CI configuration — [#2](https://github.com/mesilov/bitrix24-php-lib/pull/2)
- Fix incorrect annotation syntax from `#[\Override]` to `#[Override]` — [#3](https://github.com/mesilov/bitrix24-php-lib/pull/3)
- Rename package and namespaces to `bitrix24-php-lib` — [#4](https://github.com/mesilov/bitrix24-php-lib/pull/4)
- Add docker structure — [#13](https://github.com/mesilov/bitrix24-php-lib/pull/13)
- Add application install — [#43](https://github.com/mesilov/bitrix24-php-lib/pull/43)

---

### By [@KarlsonComplete](https://github.com/KarlsonComplete)

- Add docker containers — [#12](https://github.com/mesilov/bitrix24-php-lib/pull/12)
- Add docker
  structure —  [#14](https://github.com/mesilov/bitrix24-php-lib/pull/14), [#15](https://github.com/mesilov/bitrix24-php-lib/pull/15),   [#16](https://github.com/mesilov/bitrix24-php-lib/pull/16), [#17](https://github.com/mesilov/bitrix24-php-lib/pull/17),  [#19](https://github.com/mesilov/bitrix24-php-lib/pull/19), [#27](https://github.com/mesilov/bitrix24-php-lib/pull/27),   [#29](https://github.com/mesilov/bitrix24-php-lib/pull/29), [#32](https://github.com/mesilov/bitrix24-php-lib/pull/32),   [#34](https://github.com/mesilov/bitrix24-php-lib/pull/34), [#36](https://github.com/mesilov/bitrix24-php-lib/pull/36),   [#37](https://github.com/mesilov/bitrix24-php-lib/pull/37),  [#38](https://github.com/mesilov/bitrix24-php-lib/pull/38)
- Added mapping, fixing functional tests — [#18](https://github.com/mesilov/bitrix24-php-lib/pull/18)
- Removed attributes in the account — [#20](https://github.com/mesilov/bitrix24-php-lib/pull/20)
- Fixed some errors in functional tests — [#21](https://github.com/mesilov/bitrix24-php-lib/pull/21)
- Added fetcher test and removed more comments — [#22](https://github.com/mesilov/bitrix24-php-lib/pull/22)
- Fixes — [#23](https://github.com/mesilov/bitrix24-php-lib/pull/23)
- Fixes for scope — [#24](https://github.com/mesilov/bitrix24-php-lib/pull/24)
- Update fetcher and flusher — [#25](https://github.com/mesilov/bitrix24-php-lib/pull/25)
- Add application install — [#40](https://github.com/mesilov/bitrix24-php-lib/pull/40)  
