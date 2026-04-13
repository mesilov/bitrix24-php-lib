# bitrix24-php-lib

PHP library for Bitrix24 application development.

## Build status

| CI\CD [status](https://github.com/mesilov/bitrix24-php-lib/actions) on `main`                                                                                                                                  |
|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [![allowed licenses check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/license-check.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/license-check.yml)     |
| [![php-cs-fixer check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-cs-fixer.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-cs-fixer.yml)         |
| [![phpstan check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-phpstan.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-phpstan.yml)                |
| [![rector check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-rector.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-rector.yml)                   |
| [![unit-tests status](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-unit.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-unit.yml)                |
| [![functional-tests status](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-functional.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-functional.yml) |

## Application Domain

The library is designed for rapid development of Bitrix24 applications. It provides a storage layer in
[PostgreSQL](https://www.postgresql.org/) using [Doctrine ORM](https://www.doctrine-project.org/).

The package implements and extends
[bitrix24-php-sdk application contracts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts).

## Supported Bounded Contexts

### Bitrix24Accounts — ✅

Implements
[Bitrix24Accounts contracts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/Bitrix24Accounts)
for storing Bitrix24 portal accounts and access credentials.

Main entity:

- `Bitrix24Account`

Main use cases:

- `InstallStart`
- `InstallFinish`
- `RenewAuthToken`
- `ChangeDomainUrl`
- `UpdateVersion`
- `Uninstall`

### ApplicationInstallations — ✅

Implements
[ApplicationInstallations contracts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/ApplicationInstallations)
for storing application installation facts and install lifecycle state.

Main entity:

- `ApplicationInstallation`

Main use cases:

- `Install`
- `OnAppInstall`
- `Uninstall`
- `InstallContactPerson`
- `UnlinkContactPerson`

Reference docs:

- `src/ApplicationInstallations/Docs/application-installations.md`

### ContactPersons — ✅

Implements
[ContactPersons contracts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/ContactPersons)
for storing people related to application installation.

Main entity and enum:

- `ContactPerson`
- `ContactPersonType` (`personal` / `partner`)

Main use cases:

- `ChangeProfile`
- `MarkEmailAsVerified`
- `MarkMobilePhoneAsVerified`

### ApplicationSettings — ✅

Implements
[ApplicationSettings contracts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/ApplicationSettings)
for storing application settings per installation and per scope.

Main entity and enum:

- `ApplicationSettingsItem`
- `ApplicationSettingStatus`

Main services:

- `SettingsFetcher`
- `DefaultSettingsInstaller`

Main use cases:

- `Create`
- `Update`
- `Delete`
- `OnApplicationDelete`

Reference docs:

- `src/ApplicationSettings/Docs/application-settings.md`

### Journal — ✅

Library-specific bounded context for technical journal entries.

Main entity model:

- `JournalItem`
- `Context`
- `LogLevel`

Main services and infrastructure:

- `JournalLogger`
- `JournalItemRepositoryInterface`
- `DoctrineDbalJournalItemRepository`

Reference docs:

- `src/Journal/Docs/README.md`

### Shared Value Objects

- `Bitrix24\Lib\Common\ValueObjects\Domain`

### Not Implemented Yet

- `Bitrix24Partners` contracts are not implemented in the current package version

## Architecture

### Layers and Abstraction Levels

```text
bitrix24-app-laravel-skeleton  - Laravel application template
bitrix24-app-symfony-skeleton  - Symfony application template
bitrix24-php-lib               - domain entities, use cases, services, and persistence
bitrix24-php-sdk               - transport layer and transport events
```

### Current Source Tree

```text
src/
    ApplicationInstallations/
        Docs/
        Entity/
        Infrastructure/
        UseCase/
    ApplicationSettings/
        Docs/
        Entity/
        Events/
        Infrastructure/
        Services/
        UseCase/
    Bitrix24Accounts/
        Entity/
        Infrastructure/
        UseCase/
    Common/
        ValueObjects/
    ContactPersons/
        Entity/
        Enum/
        Infrastructure/
        UseCase/
    Journal/
        Docs/
        Entity/
        Infrastructure/
        Services/
    Services/
```

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Make

### MCP Servers

The project contains project-level MCP server configuration in `.mcp.json`.

Developers using Claude Code or Codex must verify the MCP configuration before starting work on the repository.

Configured servers:

- `bitrix24-dev` - HTTP MCP server at `https://mcp-dev.bitrix24.tech/mcp`

Recommended checks:

- ensure `.mcp.json` is present and contains the expected server list
- restart the client after pulling changes to `.mcp.json`
- verify server availability in the client before work starts

### Running Tests And Linters

Use only `Makefile` entrypoints.

```bash
# First-time setup
make docker-init

# Start containers
make docker-up

# Run tests
make test-unit
make test-functional

# Run all linters
make lint-all
```

Useful additional targets:

- `make docker-down`
- `make doctrine-schema-drop`
- `make doctrine-schema-create`
- `make php-cli-bash`

### Database Configuration

Default database credentials are pre-configured in `.env`:

- Host: `database`
- Database: `b24phpLibTest`
- User: `b24phpLibTest`
- Password: `b24phpLibTest`

No additional configuration is needed for the default local test run.

## Infrastructure

- library is cloud-agnostic

## Development Rules

1. We use linters.
2. The library is covered with tests.
3. All work is organized through issues.
4. Development processes are remote-first.
5. Think and discuss, then write.
