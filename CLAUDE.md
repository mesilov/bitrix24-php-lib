# Claude Code Knowledge Base - bitrix24-php-lib

## Project Overview
PHP library for rapid Bitrix24 application development using PostgreSQL and Doctrine ORM.

## Architecture
- **Domain-Driven Design** with bounded contexts
- **CQRS** pattern (Command/Handler)
- **Event-driven** architecture
- **Clean Architecture** principles

## Tech Stack
- **PHP 8.3** (strict requirements)
- **Doctrine ORM 3** for database operations
- **Symfony** components (Console, Cache, Event Dispatcher, Serializer, UID, etc.)
- **PostgreSQL** as primary database
- **Docker** for development environment

## Development Tools
- **PHPStan** (level 5) - static analysis
- **PHPUnit** - testing framework
- **Rector** - code refactoring
- **PHP-CS-Fixer** - code formatting
- **Composer** - dependency management

## Key Commands
```bash
# Development environment
make init                    # Initialize project
make up                     # Start containers
make down                   # Stop containers

# Code quality
make lint-phpstan           # Run PHPStan analysis
make lint-cs-fixer         # Check code style
make lint-cs-fixer-fix     # Fix code style
make lint-rector           # Check for refactoring opportunities
make lint-rector-fix       # Apply Rector refactoring

# Testing
make test-run-unit         # Run unit tests
make test-run-functional   # Run functional tests (requires DB)

# Database operations
make schema-drop           # Drop database schema
make schema-create         # Create database schema
```

## Project Structure
```
src/
├── Bitrix24Accounts/          # Account management bounded context
│   ├── Entity/                # Domain entities
│   ├── Infrastructure/        # Infrastructure layer (repositories)
│   ├── UseCase/              # CQRS commands/handlers
│   └── ValueObjects/         # Value objects
├── ApplicationInstallations/  # Installation management bounded context
│   ├── Entity/
│   ├── Infrastructure/
│   └── UseCase/
├── Services/                 # Shared services
└── Resources/config/         # Configuration files
```

## Bounded Contexts
1. **Bitrix24Accounts** - Bitrix24 account and access token management
2. **ApplicationInstallations** - Application installation tracking
3. **ContactPersons** - Contact person management
4. **Bitrix24Partners** - Partner management

## Code Standards
- Strict typing (`declare(strict_types=1)`)
- Readonly classes for commands
- Comprehensive validation in command constructors
- Interface-based dependencies
- PSR-4 autoloading
- Namespace: `Bitrix24\Lib\`

## Testing Strategy
- **Unit tests** for business logic
- **Functional tests** with real database
- Test builders for entity creation
- Separate test database configuration

## Database Configuration
- Uses Doctrine ORM with XML mapping
- Entity mappings in `config/xml/`
- Migration support via Doctrine Migrations

## Development Workflow
1. Always run linters before committing
2. Ensure all tests pass
3. Follow DDD principles
4. Use CQRS for write operations
5. Validate all inputs in command constructors
6. **After each refactoring task, automatically run linters and tests:**
   - Run all linters: `make lint-phpstan && make lint-cs-fixer && make lint-rector`
   - Run unit tests: `make test-run-unit`
   - Run functional tests: `make test-run-functional`
   - Fix any errors before proceeding to the next task
7. After refactoring, summarize changes in `changelog.md`
8. Check and actualize documentation in related files and README

## Git Workflow
- Main branch: `main`
- Feature branches: `feature/issue-number-description`

## Docker Setup
- PHP CLI container for development
- PostgreSQL database
- All commands run through Docker Compose

## Dependencies
- Core: `bitrix24/b24phpsdk` (dev-dev branch)
- Framework: Symfony components
- Database: Doctrine ORM 3
- Utilities: Carbon, Money, LibPhoneNumber, IP handling

## Environment Variables
Located in `.env` and `.env.local` files for database configuration.

### Default Configuration for Functional Tests
The `.env` file contains default values that work out-of-the-box with Docker Compose:
- `DATABASE_HOST=database` (Docker service name)
- `DATABASE_USER=b24phpLibTest`
- `DATABASE_PASSWORD=b24phpLibTest`
- `DATABASE_NAME=b24phpLibTest`
- `POSTGRES_VERSION=16`

These defaults allow running functional tests immediately after `make up` without additional configuration.
- Always update changelog.md