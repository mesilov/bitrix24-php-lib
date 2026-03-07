## Исправление падений тестов после обновления SDK (Task #77)

### Причина

После обновления зависимостей SDK (`bitrix24/b24phpsdk`) изменился контракт `ContactPersonInterface`:

- **`getBitrix24UserId()`** теперь возвращает `int` (было `?int`)
- **`createContactPersonImplementation()`** в обоих абстрактных тест-классах SDK (`ContactPersonInterfaceTest`, `ContactPersonRepositoryInterfaceTest`) изменила порядок параметров: `int $bitrix24UserId` переместился на **позицию 5** (после `$contactPersonStatus`), тип стал ненулевым

**Результат:** 58 падений `TypeError` в `make test-unit`.
**Потенциально:** аналогичные ошибки в `make test-functional` в `ContactPersonRepositoryTest`.

---

### Изменяемые файлы (4 файла)

---

#### 1. `src/ContactPersons/Entity/ContactPerson.php`

**Проблема:** `getBitrix24UserId()` возвращает `?int`, но `ContactPersonInterface` теперь требует `int`.
Конструктор уже корректен (`private readonly int $bitrix24UserId`).

**Исправление:** изменить возвращаемый тип с `?int` на `int`.

```php
// ДО
public function getBitrix24UserId(): ?int

// ПОСЛЕ
public function getBitrix24UserId(): int
```

---

#### 2. `tests/Unit/ContactPersons/Entity/ContactPersonTest.php`

**Проблема:** `createContactPersonImplementation()` — старый порядок параметров и тип `?int $bitrix24UserId`.

**Новая сигнатура** (из `vendor/.../ContactPersonInterfaceTest.php`, строки 35–54):
```
pos 1:  Uuid $uuid
pos 2:  CarbonImmutable $createdAt
pos 3:  CarbonImmutable $updatedAt
pos 4:  ContactPersonStatus $contactPersonStatus
pos 5:  int $bitrix24UserId         ← ПЕРЕМЕЩЁН сюда, ненулевой
pos 6:  string $name
pos 7:  ?string $surname
pos 8:  ?string $patronymic
pos 9:  ?string $email
pos 10: ?CarbonImmutable $emailVerifiedAt
pos 11: ?string $comment
pos 12: ?PhoneNumber $phoneNumber
pos 13: ?CarbonImmutable $mobilePhoneVerifiedAt
pos 14: ?string $externalId
pos 15: ?Uuid $bitrix24PartnerUuid
pos 16: ?string $userAgent
pos 17: ?string $userAgentReferer
pos 18: ?IP $userAgentIp
```

**Исправление:** привести сигнатуру метода к новому контракту.
Тело метода: `$bitrix24UserId` передаётся в `new ContactPerson(...)` на позицию 10 (аргумент #10 конструктора) — правильно.

---

#### 3. `tests/Functional/ContactPersons/Infrastructure/Doctrine/ContactPersonRepositoryTest.php`

**Проблема:** `createContactPersonImplementation()` — та же самая старая сигнатура (строки 27–62).
Наследует от `ContactPersonRepositoryInterfaceTest`, которая также обновила сигнатуру (см. `vendor/.../Repository/ContactPersonRepositoryInterfaceTest.php`, строки 37–55):
```
pos 5: int $bitrix24UserId  ← ненулевой, на позиции 5
pos 6: string $name
...
```

**Исправление:** привести сигнатуру и тело метода к новому контракту — аналогично п.2.

---

#### 4. `tests/Functional/ContactPersons/Builders/ContactPersonBuilder.php`

**Проблема:** поле `private ?int $bitrix24UserId = null;` (строка 33) — тип `?int`, который передаётся в `ContactPerson::__construct()` (требует `int`). PHPStan будет ругаться.

**Исправление:** изменить тип поля на `int` (значение по умолчанию убрать, инициализация уже в `__construct()`).

```php
// ДО
private ?int $bitrix24UserId = null;

// ПОСЛЕ
private int $bitrix24UserId;
```

---

### Шаги реализации

1. `src/ContactPersons/Entity/ContactPerson.php` — изменить тип возврата `getBitrix24UserId()`.
2. `tests/Unit/ContactPersons/Entity/ContactPersonTest.php` — обновить сигнатуру `createContactPersonImplementation()`.
3. `tests/Functional/ContactPersons/Infrastructure/Doctrine/ContactPersonRepositoryTest.php` — обновить сигнатуру `createContactPersonImplementation()`.
4. `tests/Functional/ContactPersons/Builders/ContactPersonBuilder.php` — исправить тип поля `$bitrix24UserId`.

---

### Проверка

```bash
# Unit-тесты (должны пройти все 170)
make test-unit

# Линтеры
make lint-phpstan
make lint-cs-fixer
make lint-rector

# Функциональные тесты (требуют БД)
make test-functional
```

---

### Примечания

- `InstallContactPerson\Command` и `Handler` не требуют изменений.
- Doctrine-маппинг (`config/xml/ContactPerson.xml`) не требует изменений.
- `CHANGELOG.md` — обновить после внесения правок.
