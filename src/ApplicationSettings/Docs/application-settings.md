# ApplicationSettings - Application Configuration Management

## Overview

ApplicationSettings is a bounded context designed for storing and managing Bitrix24 application settings using Domain-Driven Design and CQRS patterns.

## Core Concepts

### 1. Bounded Context

ApplicationSettings is a separate bounded context that encapsulates all application settings management logic.

### 2. Setting Scopes

The system supports three levels of settings:

#### Global Settings
Applied to the entire application installation, available to all users.

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Command as CreateCommand;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Handler as CreateHandler;
use Symfony\Component\Uid\Uuid;

// Create global setting
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'app.language',
    value: 'en',
    isRequired: true  // Required setting
);

$handler->handle($command);
```

#### Personal Settings
Tied to a specific Bitrix24 user.

```php
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'user.theme',
    value: 'dark',
    isRequired: false,
    b24UserId: 123  // User ID
);

$handler->handle($command);
```

#### Departmental Settings
Tied to a specific department.

```php
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'department.workingHours',
    value: '9:00-18:00',
    isRequired: false,
    b24DepartmentId: 456  // Department ID
);

$handler->handle($command);
```

### 3. Setting Status

Each setting has a status (enum `ApplicationSettingStatus`):

- **Active** - active setting, available for use
- **Deleted** - soft-deleted setting

### 4. Soft Delete

The system uses the soft-delete pattern:
- Settings are not physically deleted from the database
- When deleted, status changes to `Deleted`
- This allows preserving history and restoring data if needed

### 5. Invariants (Constraints)

**Key Uniqueness:** The combination of `applicationInstallationId + key + b24UserId + b24DepartmentId` must be unique.

This means:
- ✅ You can have a global setting `app.theme`
- ✅ You can have a personal setting `app.theme` for user 123
- ✅ You can have a personal setting `app.theme` for user 456
- ✅ You can have a departmental setting `app.theme` for department 789
- ❌ You cannot create two global settings with key `app.theme` for one installation
- ❌ You cannot create two personal settings with key `app.theme` for one user

This constraint is enforced:
- At the database level through UNIQUE INDEX
- At the application level through validation in UseCase\Create\Handler and UseCase\Update\Handler

## Data Structure

### ApplicationSettingsItem Entity Fields

```php
class ApplicationSettingsItem
{
    private Uuid $id;                           // UUID v7
    private Uuid $applicationInstallationId;     // Link to installation
    private string $key;                         // Key (only a-z and dots)
    private string $value;                       // Value (any string, JSON)
    private bool $isRequired;                    // Is setting required
    private ?int $b24UserId;                     // User ID (for personal)
    private ?int $b24DepartmentId;               // Department ID (for departmental)
    private ?int $changedByBitrix24UserId;       // Who last modified
    private ApplicationSettingStatus $status;    // Status (active/deleted)
    private CarbonImmutable $createdAt;         // Creation date
    private CarbonImmutable $updatedAt;         // Update date
}
```

### Database Table

Table: `application_settings`

### Key Validation Rules

- Only lowercase latin letters (a-z) and dots
- Maximum length 255 characters
- Recommended format: `category.subcategory.name`

Valid key examples:
```php
'app.version'
'user.interface.theme'
'notification.email.enabled'
'integration.api.timeout'
```

## Use Cases (Commands)

### Create - Creating New Setting

Creates a new setting. If a setting with the same key and scope already exists, throws an exception.

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Handler;

$command = new Command(
    applicationInstallationId: $installationId,
    key: 'feature.analytics',
    value: 'enabled',
    isRequired: true,
    b24UserId: null,
    b24DepartmentId: null,
    changedByBitrix24UserId: 100  // Who creates the setting
);

$handler->handle($command);
```

**Important:** Create will throw `SettingsItemAlreadyExistsException` if the setting already exists for the given scope.

### Update - Updating Existing Setting

Updates the value of an existing setting. If the setting is not found, throws an exception.

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Update\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Update\Handler;

$command = new Command(
    applicationInstallationId: $installationId,
    key: 'feature.analytics',
    value: 'disabled',
    b24UserId: null,
    b24DepartmentId: null,
    changedByBitrix24UserId: 100  // Who makes the change
);

$handler->handle($command);
```

**Important:** Update automatically emits `ApplicationSettingsItemChangedEvent` when the value changes.

### Delete - Soft Delete Setting

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Delete\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Delete\Handler;

$command = new Command(
    applicationInstallationId: $installationId,
    key: 'deprecated.setting',
    b24UserId: null,        // Optional
    b24DepartmentId: null   // Optional
);

$handler->handle($command);
// Setting is marked as deleted, but remains in DB
```

### OnApplicationDelete - Delete All Settings on Uninstall

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete\Handler;

// When application is uninstalled
$command = new Command(
    applicationInstallationId: $installationId
);

$handler->handle($command);
// All settings marked as deleted
```

## Working with Repository

### Finding Settings

```php
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;

/** @var ApplicationSettingsItemRepository $repository */

// Get all active settings for installation
$allSettings = $repository->findAllForInstallation($installationId);

// Find global setting by key
$globalSetting = null;
foreach ($allSettings as $s) {
    if ($s->getKey() === 'app.version' && $s->isGlobal()) {
        $globalSetting = $s;
        break;
    }
}

// Find user's personal setting
$personalSetting = null;
foreach ($allSettings as $s) {
    if ($s->getKey() === 'user.theme' && $s->isPersonal() && $s->getB24UserId() === $userId) {
        $personalSetting = $s;
        break;
    }
}

// Filter all global settings
$globalSettings = array_filter(
    $allSettings,
    fn($s): bool => $s->isGlobal()
);

// Filter user's personal settings
$personalSettings = array_filter(
    $allSettings,
    fn($s): bool => $s->isPersonal() && $s->getB24UserId() === $userId
);

// Filter department settings
$deptSettings = array_filter(
    $allSettings,
    fn($s): bool => $s->isDepartmental() && $s->getB24DepartmentId() === $deptId
);
```

**Important:** All find* methods return only settings with `Active` status. Deleted settings are not returned.

## SettingsFetcher Service

Utility for retrieving settings with cascading resolution (Personal → Departmental → Global) and automatic deserialization to objects.

### Key Features

1. **Cascading resolution**: Personal → Departmental → Global
2. **Automatic deserialization** of JSON to objects via Symfony Serializer
3. **Logging** of all operations for debugging

### Getting String Value

```php
use Bitrix24\Lib\ApplicationSettings\Services\SettingsFetcher;

/** @var SettingsFetcher $fetcher */

// Get value with priority resolution
try {
    $value = $fetcher->getValue(
        uuid: $installationId,
        key: 'app.theme',
        userId: 123,           // Optional
        departmentId: 456      // Optional
    );
    // Returns personal setting if exists
    // Otherwise departmental if exists
    // Otherwise global
} catch (SettingsItemNotFoundException $e) {
    // Setting not found at any level
}
```

### Deserialization to Object

The `getValue` method supports automatic JSON deserialization to objects:

```php
// Define DTO class
class ApiConfig
{
    public function __construct(
        public string $endpoint,
        public int $timeout,
        public int $maxRetries
    ) {}
}

// Deserialize setting to object
try {
    $config = $fetcher->getValue(
        uuid: $installationId,
        key: 'api.config',
        class: ApiConfig::class  // Specify class for deserialization
    );

    // $config is now an instance of ApiConfig
    echo $config->endpoint;  // https://api.example.com
    echo $config->timeout;   // 30
} catch (SettingsItemNotFoundException $e) {
    // Setting not found
}
```

### Getting Full Setting Object

If you need access to metadata (id, createdAt, updatedAt, scope, etc.):

```php
$item = $fetcher->getItem(
    uuid: $installationId,
    key: 'app.theme',
    userId: 123,
    departmentId: 456
);

// Access metadata
$settingId = $item->getId();
$createdAt = $item->getCreatedAt();
$isPersonal = $item->isPersonal();
$value = $item->getValue();
```

## Events

### ApplicationSettingsItemChangedEvent

Emitted when a setting value changes (via Update use case or updateValue() method on entity):

```php
class ApplicationSettingsItemChangedEvent
{
    public Uuid $settingId;
    public string $key;
    public string $oldValue;
    public string $newValue;
    public ?int $changedByBitrix24UserId;
    public CarbonImmutable $changedAt;
}
```

Events can be captured for logging, auditing, or triggering other actions:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SettingChangeLogger implements EventSubscriberInterface
{
    public function onSettingChanged(ApplicationSettingsItemChangedEvent $event): void
    {
        $this->logger->info('Setting changed', [
            'key' => $event->key,
            'old' => $event->oldValue,
            'new' => $event->newValue,
            'changedBy' => $event->changedByBitrix24UserId,
        ]);
    }
}
```

## DefaultSettingsInstaller Service

Utility for creating a set of default settings during application installation:

```php
use Bitrix24\Lib\ApplicationSettings\Services\DefaultSettingsInstaller;

// Create all settings for new installation
$installer = new DefaultSettingsInstaller(
    $createHandler,
    $logger
);

$installer->createDefaultSettings(
    uuid: $installationId,
    defaultSettings: [
        'app.name' => ['value' => 'My App', 'required' => true],
        'app.language' => ['value' => 'en', 'required' => true],
        'features.notifications' => ['value' => 'true', 'required' => false],
    ]
);
```

**Important:** DefaultSettingsInstaller uses Create use case, so if a setting already exists, an exception will be thrown.

## CLI Commands

### Viewing Settings

```bash
# All installation settings
php bin/console app:settings:list <installation-id>

# Only global
php bin/console app:settings:list <installation-id> --global-only

# User's personal
php bin/console app:settings:list <installation-id> --user-id=123

# Departmental
php bin/console app:settings:list <installation-id> --department-id=456
```

## Usage Examples

### Example 1: Creating and Updating Setting

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Create\Command as CreateCommand;
use Bitrix24\Lib\ApplicationSettings\UseCase\Update\Command as UpdateCommand;

// Create new setting
$createCmd = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'integration.api.config',
    value: json_encode([
        'endpoint' => 'https://api.example.com',
        'timeout' => 30,
    ]),
    isRequired: true
);
$createHandler->handle($createCmd);

// Update existing setting
$updateCmd = new UpdateCommand(
    applicationInstallationId: $installationId,
    key: 'integration.api.config',
    value: json_encode([
        'endpoint' => 'https://api.example.com',
        'timeout' => 60,  // Changed timeout
        'retries' => 3,   // Added retries
    ]),
    changedByBitrix24UserId: 100
);
$updateHandler->handle($updateCmd);
```

### Example 2: Storing and Deserializing JSON Configuration

```php
// Create setting with JSON value
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'integration.api.config',
    value: json_encode([
        'endpoint' => 'https://api.example.com',
        'timeout' => 30,
        'retries' => 3,
    ]),
    isRequired: true
);
$handler->handle($command);

// Read as string
$value = $fetcher->getValue($installationId, 'integration.api.config');
$config = json_decode($value, true);

// OR automatic deserialization to object
class ApiConfig
{
    public function __construct(
        public string $endpoint,
        public int $timeout,
        public int $retries
    ) {}
}

$config = $fetcher->getValue(
    uuid: $installationId,
    key: 'integration.api.config',
    class: ApiConfig::class
);

// Use typed object
echo $config->endpoint;  // https://api.example.com
echo $config->timeout;   // 30
```

### Example 3: UI Personalization

```php
// Save user preferences
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'ui.preferences',
    value: json_encode([
        'theme' => 'dark',
        'language' => 'en',
        'dashboard_layout' => 'compact',
    ]),
    isRequired: false,
    b24UserId: $currentUserId,
    changedByBitrix24UserId: $currentUserId
);
$handler->handle($command);

// Get preferences with personal settings priority
try {
    $value = $fetcher->getValue(
        uuid: $installationId,
        key: 'ui.preferences',
        userId: $currentUserId
    );
    $preferences = json_decode($value, true);
} catch (SettingsItemNotFoundException $e) {
    $preferences = []; // Defaults
}
```

### Example 4: Cascading Resolution

```php
use Bitrix24\Lib\ApplicationSettings\Services\SettingsFetcher;

/**
 * SettingsFetcher automatically uses priorities:
 * 1. Personal (if userId provided and setting exists)
 * 2. Departmental (if departmentId provided and setting exists)
 * 3. Global (fallback)
 */

$value = $fetcher->getValue(
    uuid: $installationId,
    key: 'notification.email.enabled',
    userId: 123,
    departmentId: 456
);

// If personal setting exists for user 123 - returns it
// Otherwise if departmental exists for dept 456 - returns it
// Otherwise returns global
// If none found - throws SettingsItemNotFoundException
```

### Example 5: Change Auditing

```php
// When creating setting, specify who created it
$createCmd = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'security.two_factor',
    value: 'disabled',
    isRequired: true,
    changedByBitrix24UserId: $adminUserId
);
$createHandler->handle($createCmd);

// When updating setting, specify who changed it
$updateCmd = new UpdateCommand(
    applicationInstallationId: $installationId,
    key: 'security.two_factor',
    value: 'enabled',
    changedByBitrix24UserId: $adminUserId
);
$updateHandler->handle($updateCmd);

// Events are automatically logged with information about who made the change
```

## Best Practices

### 1. Key Naming

Use clear, hierarchical names:

```php
// Good
'app.feature.notifications.email'
'user.interface.theme'
'integration.crm.enabled'

// Bad
'notif'
'th'
'crm1'
```

### 2. Value Typing

Store JSON for complex structures:

```php
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'feature.limits',
    value: json_encode([
        'users' => 100,
        'storage_gb' => 50,
        'api_calls_per_day' => 10000,
    ]),
    isRequired: true
);
```

### 3. Required Settings

Mark critical settings as `isRequired`:

```php
$command = new CreateCommand(
    applicationInstallationId: $installationId,
    key: 'app.license_key',
    value: $licenseKey,
    isRequired: true  // Application won't work without this
);
```

### 4. Separating Create and Update

Always use the correct use case:

```php
// ✅ For creating new settings
$createHandler->handle(new CreateCommand(...));

// ✅ For modifying existing settings
$updateHandler->handle(new UpdateCommand(...));

// ❌ DON'T use Create for updates
// This will throw SettingsItemAlreadyExistsException
```

### 5. Soft Delete

Use soft-delete instead of physical deletion:

```php
// Use soft delete
$deleteCommand = new DeleteCommand($installationId, 'old.setting');
$deleteHandler->handle($deleteCommand);
```

### 6. Exception Handling

```php
use Bitrix24\Lib\ApplicationSettings\Services\Exception\SettingsItemNotFoundException;
use Bitrix24\Lib\ApplicationSettings\Services\Exception\SettingsItemAlreadyExistsException;

// Create may throw SettingsItemAlreadyExistsException if setting exists
try {
    $createHandler->handle($createCommand);
} catch (SettingsItemAlreadyExistsException $e) {
    // Setting already exists, use Update instead
}

// Update may throw SettingsItemNotFoundException if setting not found
try {
    $updateHandler->handle($updateCommand);
} catch (SettingsItemNotFoundException $e) {
    // Setting doesn't exist, use Create instead
}

// SettingsFetcher may throw SettingsItemNotFoundException
try {
    $value = $fetcher->getValue($uuid, $key);
} catch (SettingsItemNotFoundException $e) {
    // Use default value
}
```

## Security

1. **Key validation** - automatic, only allowed characters
2. **Data isolation** - settings tied to `applicationInstallationId`
3. **Audit trail** - tracking who and when changed (`changedByBitrix24UserId`)
4. **History** - soft-delete preserves history for investigations
5. **ACID guarantees** - all operations in Doctrine transactions

## Performance

1. **Indexes** - all key fields are indexed (installation_id, key, user_id, department_id, status)
2. **Caching** - recommended to cache frequently used settings
3. **Batch operations** - use `DefaultSettingsInstaller` for bulk creation
4. **Optimized queries** - `findAllForInstallationByKey` filters at DB level

## Database Schema Migration

After making code changes, update the database schema:

```bash
# Create schema (first time)
make schema-create

# Or generate migration
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Testing

The system is fully covered by tests:

```bash
# Unit tests
make test-run-unit

# Functional tests (requires DB)
make test-run-functional
```

---

**Additional Resources:**
- [CLAUDE.md](../../../CLAUDE.md) - Main commands and project architecture
