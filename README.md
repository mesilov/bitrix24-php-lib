# bitrix24-php-lib

PHP lib for Bitrix24 application development

## Build status

| CI\CD [status](https://github.com/mesilov/bitrix24-php-lib/actions) on `master`                                                                                                                                | 
|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------| 
| [![allowed licenses check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/license-check.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/license-check.yml)        | 
| [![php-cs-fixer check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-cs-fixer.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-cs-fixer.yml)            |
| [![phpstan check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-phpstan.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-phpstan.yml)                   | 
| [![rector check](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-rector.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/lint-rector.yml)                      |  
| [![unit-tests status](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-unit.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-unit.yml)                   | 
| [![functional-tests status](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-functional.yml/badge.svg)](https://github.com/mesilov/bitrix24-php-lib/actions/workflows/tests-functional.yml) | 

## Application Domain

The library is designed for rapid development of Bitrix24 applications. Provides data storage layer in
[PostgreSQL](https://www.postgresql.org/) database using [Doctrine ORM](https://www.doctrine-project.org/).

Implements [contracts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts) from
bitrix24-php-sdk.

## Supported Contracts

### Bitrix24Accounts — ✅

Responsible for
storing [Bitrix24 accounts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/Bitrix24Accounts)
with portal access tokens.

### ApplicationInstallations — ✅

Responsible for
storing [installation facts](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/ApplicationInstallations)
of applications on specific Bitrix24 portals

### ContactPersons — ⏳ work in progress

Responsible for
storing [contact persons](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/ContactPersons)
who performed application installation

### Bitrix24Partners — ⏳ work in progress

Responsible for
storing [Bitrix24 partners](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/Bitrix24Partners) who performed installation or service
the portal

### ApplicationSettings — ✅

Responsible for
storing [application settings](https://github.com/bitrix24/b24phpsdk/tree/main/src/Application/Contracts/ApplicationSettings)
for specific Bitrix24 portal

## Architecture

### Layers and Abstraction Levels

```
bitrix24-app-laravel-skeleton – Laravel application template
bitrix24-app-symfony-skeleton – Symfony application template    
bitrix24-php-lib – application entities work and their storage in database
bitrix24-php-sdk – transport layer + transport events (expired token, portal renamed)
```

### Bounded Context Folder Structure

```
src/
    Bitrix24Accounts
        Controllers
        Entity
        Exceptions
        Events
        EventListeners
        Infrastructure
            ConsoleCommands
            Doctrine
                Types
        Repository
        ReadModel
        UseCases
            SomeUseCase
        Tests    
```

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Make

### Running Tests

```bash
# Initialize and start services
make up

# Run functional tests (uses default database configuration)
make test-run-functional

# Run linters
make lint-phpstan
make lint-cs-fixer
make lint-rector
```

### Database Configuration

Default database credentials are pre-configured in `.env`:

- Host: `database` (Docker service)
- Database: `b24phpLibTest`
- User: `b24phpLibTest`
- Password: `b24phpLibTest`

No additional configuration needed for running tests.

## Infrastructure

- library is made cloud-agnostic

## Development Rules

1. We use linters
2. Library is covered with tests
3. All work is organized through issues
4. Development processes are remote first
5. Think and discuss — then write