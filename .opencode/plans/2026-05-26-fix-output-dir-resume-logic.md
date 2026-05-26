# Fix output-dir/output-file Resume Logic

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the ambiguous and inconsistent output-dir/output-file handling during resume mode, making the flow single-pass and deterministic.

**Architecture:** `ScrapeConfig` stops auto-generating `outputFile` in the constructor. Instead, `outputFile` is resolved explicitly in the Command layer before the config is used. `ScrapeStateManager` stores the full output file path (not dir+basename separately) and validates output-dir consistency on resume. The double-creation of `ScrapeConfig` is eliminated.

**Tech Stack:** PHP 8.3, PHPUnit

---

## Files to Create/Modify

| File | Action | Responsibility |
|---|---|---|
| `src/Bitrix24Partners/UseCase/Scrape/ScrapeConfig.php` | Modify | Remove auto-generation from constructor, make `outputFile` nullable public property |
| `src/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManager.php` | Modify | Store full path as `output_file`, add output-dir validation on resume |
| `src/Bitrix24Partners/UseCase/Scrape/ScrapeWorkflow.php` | Modify | Split `resolveStartContext` into two focused methods, adapt to nullable `outputFile` |
| `src/Bitrix24Partners/Console/ScrapePartnersCommand.php` | Modify | Single-pass resolve: determine outputFile early, create one ScrapeConfig, fix verbose output |
| `tests/Unit/Bitrix24Partners/UseCase/Scrape/ScrapeConfigTest.php` | Create | Unit tests for ScrapeConfig outputFile resolution |
| `tests/Unit/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManagerTest.php` | Create | Unit tests for state manager resume, validation, initState |

---

### Task 1: Refactor ScrapeConfig — remove auto-generation from constructor

**Files:**
- Modify: `src/Bitrix24Partners/UseCase/Scrape/ScrapeConfig.php`
- Create: `tests/Unit/Bitrix24Partners/UseCase/Scrape/ScrapeConfigTest.php`

- [ ] **Step 1: Write failing tests for new ScrapeConfig behavior**

Create `tests/Unit/Bitrix24Partners/UseCase/Scrape/ScrapeConfigTest.php`:

```php
<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape\ScrapeConfig;
use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScrapeConfig::class)]
class ScrapeConfigTest extends TestCase
{
    #[Test]
    public function outputFileIsNullByDefault(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            catalogPageDelay: 2,
            partnerDetailDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertNull($config->outputFile);
    }

    #[Test]
    public function outputFileCanBeSetExplicitly(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            catalogPageDelay: 2,
            partnerDetailDelay: 2,
            insecure: false,
            resume: false,
            outputFile: '/tmp/test/partners-20260526-120000.csv',
        );

        $this->assertSame('/tmp/test/partners-20260526-120000.csv', $config->outputFile);
    }

    #[Test]
    public function generateTimestampedPathProducesPathWithDir(): void
    {
        $path = ScrapeConfig::generateTimestampedPath('/tmp/test');

        $this->assertStringStartsWith('/tmp/test/partners-', $path);
        $this->assertStringEndsWith('.csv', $path);
    }

    #[Test]
    public function generateTimestampedPathStripsTrailingSlash(): void
    {
        $path = ScrapeConfig::generateTimestampedPath('/tmp/test/');

        $this->assertStringStartsWith('/tmp/test/partners-', $path);
        $this->assertDoesNotMatchRegularExpression('|//partners-|', $path);
    }

    #[Test]
    public function baseUrlDefaultsToZoneUrl(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            catalogPageDelay: 2,
            partnerDetailDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertSame(Bitrix24Zone::RU->getPartnerListUrl(), $config->baseUrl);
    }

    #[Test]
    public function isUpdateModeReturnsTrueWithPartnerIds(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            catalogPageDelay: 2,
            partnerDetailDelay: 2,
            insecure: false,
            resume: false,
            partnerIds: [1, 2, 3],
        );

        $this->assertTrue($config->isUpdateMode());
    }

    #[Test]
    public function isUpdateModeReturnsFalseWithoutPartnerIds(): void
    {
        $config = new ScrapeConfig(
            zone: Bitrix24Zone::RU,
            outputDir: '/tmp/test',
            catalogPageDelay: 2,
            partnerDetailDelay: 2,
            insecure: false,
            resume: false,
        );

        $this->assertFalse($config->isUpdateMode());
    }
}
```

Run: `make test-run-unit -- tests/Unit/Bitrix24Partners/UseCase/Scrape/ScrapeConfigTest.php`
Expected: FAIL — `outputFile` is currently `string` (not nullable), auto-generated in constructor.

- [ ] **Step 2: Modify ScrapeConfig to make outputFile nullable and remove auto-generation**

Replace the entire `src/Bitrix24Partners/UseCase/Scrape/ScrapeConfig.php`:

```php
<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Scrape;

use Bitrix24\Lib\Bitrix24Partners\ValueObjects\Bitrix24Zone;
use Carbon\CarbonImmutable;

readonly class ScrapeConfig
{
    public readonly string $baseUrl;

    /**
     * @param null|array<int> $partnerIds
     */
    public function __construct(
        public Bitrix24Zone $zone,
        public string $outputDir,
        public int $catalogPageDelay,
        public int $partnerDetailDelay,
        public bool $insecure,
        public bool $resume,
        public ?array $partnerIds = null,
        ?string $baseUrl = null,
        public ?string $outputFile = null,
    ) {
        $this->baseUrl = $baseUrl ?? $zone->getPartnerListUrl();
    }

    public function isUpdateMode(): bool
    {
        return null !== $this->partnerIds && [] !== $this->partnerIds;
    }

    public static function generateTimestampedPath(string $outputDir): string
    {
        return rtrim($outputDir, '/').'/partners-'.CarbonImmutable::now()->format('Ymd-His').'.csv';
    }
}
```

Key changes:
- `outputFile` is now `?string` (nullable), promoted directly to public readonly property
- Constructor no longer calls `generateTimestampedPath()` — this is now the caller's responsibility
- `generateTimestampedPath()` remains as a static factory helper

- [ ] **Step 3: Run tests to verify they pass**

Run: `make test-run-unit -- tests/Unit/Bitrix24Partners/UseCase/Scrape/ScrapeConfigTest.php`
Expected: PASS (all 7 tests)

- [ ] **Step 4: Run existing ScrapeResult and PartnerData tests to ensure nothing broke**

Run: `make test-run-unit -- tests/Unit/Bitrix24Partners/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Bitrix24Partners/UseCase/Scrape/ScrapeConfig.php tests/Unit/Bitrix24Partners/UseCase/Scrape/ScrapeConfigTest.php
git commit -m "refactor: make ScrapeConfig::outputFile nullable, remove auto-generation"
```

---

### Task 2: Refactor ScrapeStateManager — full path storage + output-dir validation

**Files:**
- Modify: `src/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManager.php`
- Create: `tests/Unit/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManagerTest.php`

- [ ] **Step 1: Write failing tests for ScrapeStateManager**

Create `tests/Unit/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManagerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\Infrastructure\Scraper;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper\ScrapeStateManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScrapeStateManager::class)]
class ScrapeStateManagerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/scrape-state-test-'.uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir.'/state.json');
        @rmdir($this->tmpDir);
    }

    #[Test]
    public function initStateCreatesStateFileWithFullPath(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState(
            outputDir: $this->tmpDir,
            outputFile: $this->tmpDir.'/partners-20260526-120000.csv',
            baseUrl: 'https://partners.bitrix24.ru',
            lastPage: 42,
        );

        $stateJson = file_get_contents($this->tmpDir.'/state.json');
        $this->assertNotFalse($stateJson);
        $state = json_decode($stateJson, true);

        $this->assertSame($this->tmpDir, $state['output_dir']);
        $this->assertSame($this->tmpDir.'/partners-20260526-120000.csv', $state['output_file']);
        $this->assertSame(42, $state['total_pages']);
        $this->assertSame(0, $state['last_completed_page']);
    }

    #[Test]
    public function updateProgressUpdatesCompletedPage(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-test.csv', 'https://example.com', 10);

        $manager->updateProgress($this->tmpDir, 5);

        $stateJson = file_get_contents($this->tmpDir.'/state.json');
        $state = json_decode($stateJson, true);
        $this->assertSame(5, $state['last_completed_page']);
    }

    #[Test]
    public function resumeReturnsNullWhenNoStateFile(): void
    {
        $manager = new ScrapeStateManager();

        $result = $manager->resume($this->tmpDir);

        $this->assertNull($result);
    }

    #[Test]
    public function resumeReturnsContextFromState(): void
    {
        $manager = new ScrapeStateManager();
        $outputFile = $this->tmpDir.'/partners-test.csv';
        file_put_contents($outputFile, "bitrix24_partner_number,title\n");

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20);
        $manager->updateProgress($this->tmpDir, 9);

        $result = $manager->resume($this->tmpDir);

        $this->assertNotNull($result);
        $this->assertSame(10, $result['startPage']);
        $this->assertSame(20, $result['lastPage']);
        $this->assertSame($outputFile, $result['outputFile']);
        $this->assertIsArray($result['processedNumbers']);
    }

    #[Test]
    public function resumeThrowsWhenOutputDirMismatches(): void
    {
        $manager = new ScrapeStateManager();
        $outputFile = $this->tmpDir.'/partners-test.csv';
        file_put_contents($outputFile, "bitrix24_partner_number,title\n");

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20);

        $otherDir = '/completely/different/dir';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/output-dir не совпадает/');
        $manager->resume($otherDir);
    }

    #[Test]
    public function completeRemovesStateFile(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-test.csv', 'https://example.com', 10);

        $this->assertFileExists($this->tmpDir.'/state.json');

        $manager->complete($this->tmpDir);

        $this->assertFileDoesNotExist($this->tmpDir.'/state.json');
    }

    #[Test]
    public function initStateOverwritesExistingState(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-old.csv', 'https://example.com', 10);

        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-new.csv', 'https://example.com', 30);

        $stateJson = file_get_contents($this->tmpDir.'/state.json');
        $state = json_decode($stateJson, true);
        $this->assertSame($this->tmpDir.'/partners-new.csv', $state['output_file']);
        $this->assertSame(30, $state['total_pages']);
    }

    #[Test]
    public function resumeLoadsProcessedPartnerNumbers(): void
    {
        $manager = new ScrapeStateManager();
        $outputFile = $this->tmpDir.'/partners-test.csv';
        file_put_contents($outputFile, "bitrix24_partner_number,title\n101,\"A\"\n202,\"B\"\n");

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20);
        $manager->updateProgress($this->tmpDir, 5);

        $result = $manager->resume($this->tmpDir);

        $this->assertNotNull($result);
        $this->assertArrayHasKey(101, $result['processedNumbers']);
        $this->assertArrayHasKey(202, $result['processedNumbers']);
        $this->assertTrue($result['processedNumbers'][101]);
        $this->assertTrue($result['processedNumbers'][202]);
    }
}
```

Run: `make test-run-unit -- tests/Unit/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManagerTest.php`
Expected: FAIL — `resume()` currently reconstructs path from `output_dir` + basename, no validation, state stores basename not full path.

- [ ] **Step 2: Modify ScrapeStateManager to store full path and validate output-dir**

Replace the entire `src/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManager.php`:

```php
<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use League\Csv\Reader;

class ScrapeStateManager
{
    private ?array $state = null;

    /**
     * @return null|array{lastPage: int, startPage: int, processedNumbers: array<int, true>, outputFile: string}
     */
    public function resume(string $outputDir): ?array
    {
        $state = $this->readStateFile($outputDir);
        if (null === $state) {
            return null;
        }

        $normalizedDir = rtrim($outputDir, '/');
        $storedDir = $state['output_dir'];
        if ($normalizedDir !== $storedDir) {
            throw new \RuntimeException(sprintf(
                'output-dir не совпадает: передан "%s", ожидается "%s" (из state.json)',
                $normalizedDir,
                $storedDir,
            ));
        }

        $outputFile = $state['output_file'];
        $processedNumbers = $this->loadProcessedPartnerNumbers($outputFile);

        return [
            'lastPage' => $state['total_pages'],
            'startPage' => $state['last_completed_page'] + 1,
            'processedNumbers' => $processedNumbers,
            'outputFile' => $outputFile,
        ];
    }

    public function initState(string $outputDir, string $outputFile, string $baseUrl, int $lastPage): void
    {
        $statePath = $this->getStateFilePath($outputDir);
        if (file_exists($statePath)) {
            unlink($statePath);
        }

        $this->state = [
            'mode' => 'full_scrape',
            'base_url' => $baseUrl,
            'total_pages' => $lastPage,
            'last_completed_page' => 0,
            'output_dir' => rtrim($outputDir, '/'),
            'output_file' => $outputFile,
            'started_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updated_at' => '',
        ];
        $this->writeState($outputDir);
    }

    public function updateProgress(string $outputDir, int $completedPage): void
    {
        if (null === $this->state) {
            return;
        }

        $this->state['last_completed_page'] = $completedPage;
        $this->writeState($outputDir);
    }

    public function complete(string $outputDir): void
    {
        $statePath = $this->getStateFilePath($outputDir);
        if (file_exists($statePath)) {
            unlink($statePath);
        }

        $this->state = null;
    }

    private function getStateFilePath(string $outputDir): string
    {
        return rtrim($outputDir, '/').'/state.json';
    }

    private function readStateFile(string $outputDir): ?array
    {
        $statePath = $this->getStateFilePath($outputDir);
        if (!file_exists($statePath)) {
            return null;
        }

        $content = file_get_contents($statePath);
        if (false === $content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function writeState(string $outputDir): void
    {
        $this->state['updated_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $result = file_put_contents(
            $this->getStateFilePath($outputDir),
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if (false === $result) {
            throw new \RuntimeException(sprintf('Не удалось записать state-файл: %s', $this->getStateFilePath($outputDir)));
        }
    }

    /**
     * @return array<int, true>
     */
    private function loadProcessedPartnerNumbers(string $outputFile): array
    {
        if (!file_exists($outputFile)) {
            return [];
        }

        $reader = Reader::from($outputFile);
        $reader->setHeaderOffset(0);

        $numbers = [];
        foreach ($reader->getRecords() as $record) {
            $number = (int) ($record['bitrix24_partner_number'] ?? 0);
            if ($number > 0) {
                $numbers[$number] = true;
            }
        }

        return $numbers;
    }
}
```

Key changes from current version:
1. `initState()` now stores full path in `output_file` (was `basename($outputFile)`)
2. `resume()` no longer reconstructs path from `output_dir.'/'.$output_file` — reads `output_file` directly
3. `resume()` validates that `$outputDir` matches `$state['output_dir']` — throws `RuntimeException` on mismatch

- [ ] **Step 3: Run tests to verify they pass**

Run: `make test-run-unit -- tests/Unit/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManagerTest.php`
Expected: PASS (all 8 tests)

- [ ] **Step 4: Commit**

```bash
git add src/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManager.php tests/Unit/Bitrix24Partners/Infrastructure/Scraper/ScrapeStateManagerTest.php
git commit -m "refactor: ScrapeStateManager stores full output_file path, validates output-dir on resume"
```

---

### Task 3: Update ScrapeWorkflow — split resolveStartContext, add null assertions

**Files:**
- Modify: `src/Bitrix24Partners/UseCase/Scrape/ScrapeWorkflow.php`

- [ ] **Step 1: Replace `resolveStartContext()` with two focused methods and add null assertions**

In `ScrapeWorkflow.php`, make the following changes:

**Remove** the `resolveStartContext()` method entirely (lines 28-53).

**Add** two new public methods in its place:

```php
/**
 * @return null|array{startPage: int, lastPage: int, processedNumbers: array<int, true>, outputFile: string}
 */
public function resolveResumeContext(string $outputDir): ?array
{
    return $this->stateManager->resume($outputDir);
}

/**
 * @param null|\Closure(string): void $onVerbose
 *
 * @return array{lastPage: int, partnersPerPage: int}
 */
public function getPageRange(Bitrix24Zone $zone, bool $insecure, ?\Closure $onVerbose = null): array
{
    $baseUrl = $zone->getPartnerListUrl();
    $range = $this->scraper->getPageRange($baseUrl, $insecure, $onVerbose);

    return [
        'lastPage' => $range['lastPage'],
        'partnersPerPage' => $range['partnersPerPage'],
    ];
}
```

**Add null assertion** in `initCsvWriter()`:

```php
private function initCsvWriter(ScrapeConfig $config): Writer
{
    if (null === $config->outputFile) {
        throw new \LogicException('outputFile must be set before initializing CSV writer.');
    }

    $csvWriter = $config->resume
        ? Writer::from($config->outputFile, 'a+')
        : Writer::from($config->outputFile, 'w+');
```

**Add null assertion** in `run()` before `initState`:

```php
    if (null === $config->outputFile) {
        throw new \LogicException('outputFile must be set before running scrape.');
    }
    $this->stateManager->initState($config->outputDir, $config->outputFile, $config->baseUrl, $lastPage);
```

- [ ] **Step 2: Run all unit tests**

Run: `make test-run-unit`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Bitrix24Partners/UseCase/Scrape/ScrapeWorkflow.php
git commit -m "refactor: split resolveStartContext into resolveResumeContext + getPageRange, add null assertions"
```

---

### Task 4: Refactor ScrapePartnersCommand — single-pass resolve, fix verbose output

**Files:**
- Modify: `src/Bitrix24Partners/Console/ScrapePartnersCommand.php`

This is the most critical change. The Command now:
1. Validates raw CLI options first (no config creation)
2. Resolves `outputFile` once based on mode (resume vs fresh vs update)
3. Creates exactly ONE `ScrapeConfig` with the resolved file
4. Shows correct verbose output after resolution

- [ ] **Step 1: Replace `resolveConfig()` with `resolveOptions()`**

Remove the `resolveConfig()` method (lines 98-160). Add in its place:

```php
/**
 * @return null|array{zone: Bitrix24Zone, outputDir: string, catalogPageDelay: int, partnerDetailDelay: int, insecure: bool, partnerIds: null|array<int>}
 */
private function resolveOptions(InputInterface $input): ?array
{
    try {
        $zone = Bitrix24Zone::from($input->getOption('zone'));
    } catch (\ValueError) {
        $this->io->error(sprintf(
            'Invalid zone "%s". Allowed values: %s',
            $input->getOption('zone'),
            implode(', ', array_map(static fn (Bitrix24Zone $z) => $z->value, Bitrix24Zone::cases())),
        ));

        return null;
    }

    $partnerDetailDelay = (int) $input->getOption('partner-detail-delay');
    if ($partnerDetailDelay <= 0) {
        $this->io->error('partner-detail-delay must be greater than 0');

        return null;
    }

    $partnerIds = null;
    $partnerIdsRaw = $input->getOption('partner-ids');
    if ('' !== $partnerIdsRaw) {
        $partnerIds = [];
        $parts = array_map('trim', explode(',', (string) $partnerIdsRaw));
        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                $this->io->error(sprintf('Невалидный ID партнёра: "%s". Ожидается положительное число.', $part));

                return null;
            }

            $partnerIds[] = (int) $part;
        }
    }

    $catalogPageDelay = (int) $input->getOption('catalog-page-delay');
    if (null === $partnerIds && $catalogPageDelay <= 0) {
        $this->io->error('catalog-page-delay must be greater than 0');

        return null;
    }

    $outputDir = $input->getOption('output-dir');
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            $this->io->error(sprintf('Не удалось создать директорию: %s', $outputDir));

            return null;
        }
    }

    return [
        'zone' => $zone,
        'outputDir' => $outputDir,
        'catalogPageDelay' => $catalogPageDelay,
        'partnerDetailDelay' => $partnerDetailDelay,
        'insecure' => (bool) $input->getOption('insecure'),
        'partnerIds' => $partnerIds,
    ];
}
```

- [ ] **Step 2: Replace `execute()` method**

```php
#[\Override]
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $this->io = new SymfonyStyle($input, $output);
    $this->output = $output;

    $options = $this->resolveOptions($input);
    if (null === $options) {
        return Command::FAILURE;
    }

    $resume = (bool) $input->getOption('resume');
    $isUpdateMode = null !== $options['partnerIds'] && [] !== $options['partnerIds'];

    try {
        if ($isUpdateMode) {
            $outputFile = ScrapeConfig::generateTimestampedPath($options['outputDir']);
            $config = new ScrapeConfig(
                zone: $options['zone'],
                outputDir: $options['outputDir'],
                catalogPageDelay: $options['catalogPageDelay'],
                partnerDetailDelay: $options['partnerDetailDelay'],
                insecure: $options['insecure'],
                resume: false,
                partnerIds: $options['partnerIds'],
                outputFile: $outputFile,
            );

            return $this->executeUpdate($config);
        }

        return $this->executeFullScrape($options, $resume);
    } catch (\Throwable $throwable) {
        $this->logger->error('Ошибка: '.$throwable->getMessage());
        $this->io->error('Ошибка: '.$throwable->getMessage());

        return Command::FAILURE;
    }
}
```

- [ ] **Step 3: Replace `executeFullScrape()` method**

```php
/**
 * @param array{zone: Bitrix24Zone, outputDir: string, catalogPageDelay: int, partnerDetailDelay: int, insecure: bool, partnerIds: null|array<int>} $options
 */
private function executeFullScrape(array $options, bool $resume): int
{
    $onVerbose = $this->io->isVerbose()
        ? fn (string $message) => $this->io->text($message)
        : null;

    $outputFile = null;
    $startPage = 1;
    $lastPage = 0;
    $processedNumbers = [];
    $partnersPerPage = 12;

    if ($resume) {
        $resumeState = $this->scrapeWorkflow->resolveResumeContext($options['outputDir']);
        if (null === $resumeState) {
            $this->io->error('State-файл не найден. Запустите без --resume.');

            return Command::FAILURE;
        }

        $outputFile = $resumeState['outputFile'];
        $startPage = $resumeState['startPage'];
        $lastPage = $resumeState['lastPage'];
        $processedNumbers = $resumeState['processedNumbers'];
    } else {
        $outputFile = ScrapeConfig::generateTimestampedPath($options['outputDir']);

        $range = $this->scrapeWorkflow->getPageRange($options['zone'], $options['insecure'], $onVerbose);
        $lastPage = $range['lastPage'];
        $partnersPerPage = $range['partnersPerPage'];
    }

    $config = new ScrapeConfig(
        zone: $options['zone'],
        outputDir: $options['outputDir'],
        catalogPageDelay: $options['catalogPageDelay'],
        partnerDetailDelay: $options['partnerDetailDelay'],
        insecure: $options['insecure'],
        resume: $resume,
        partnerIds: $options['partnerIds'],
        outputFile: $outputFile,
    );

    if ($this->io->isVerbose()) {
        $this->io->text(sprintf('Zone: %s', $config->zone->value));
        $this->io->text(sprintf('Base URL: %s', $config->baseUrl));
        $this->io->text(sprintf('Output dir: %s', $config->outputDir));
        $this->io->text(sprintf('Output file: %s', $config->outputFile));
        $this->io->text(sprintf('Partner detail delay: %d sec', $config->partnerDetailDelay));
        $this->io->text(sprintf('Insecure: %s', $config->insecure ? 'yes' : 'no'));
        $this->io->text(sprintf('Resume: %s', $config->resume ? 'yes' : 'no'));

        if ($resume) {
            $this->io->note(sprintf(
                'Resume: продолжаем со страницы %d из %d (уже обработано: %d)',
                $startPage,
                $lastPage,
                count($processedNumbers)
            ));
        }
    }

    if (!$resume && $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
        $this->io->section('Определение количества страниц...');
        $this->io->success(sprintf(
            'Найдено страниц: %d | Партнёров на странице: %d (≈%d партнёров)',
            $lastPage,
            $partnersPerPage,
            $lastPage * $partnersPerPage
        ));
        $this->io->section('Парсинг партнёров...');
    }

    $progressBar = $this->createScrapeProgressBar($lastPage * $partnersPerPage, count($processedNumbers));

    $onProgress = function (string $event, int $value) use ($progressBar): void {
        match ($event) {
            'page_start' => $progressBar?->setMessage((string) $value, 'page'),
            'partner_start' => $progressBar?->setMessage((string) $value, 'partner'),
            'partner_advance' => $progressBar?->advance(),
            default => null,
        };
    };

    $result = $this->scrapeWorkflow->run(
        $config,
        $startPage,
        $lastPage,
        $processedNumbers,
        $onProgress,
    );

    return $this->finishScrape($config->outputDir, $progressBar, $result);
}
```

- [ ] **Step 4: Run all tests**

Run: `make test-run-unit`
Expected: PASS

- [ ] **Step 5: Run PHPStan**

Run: `make lint-phpstan`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Bitrix24Partners/Console/ScrapePartnersCommand.php
git commit -m "refactor: single-pass output-file resolution, eliminate double ScrapeConfig creation"
```

---

### Task 5: Final verification

- [ ] **Step 1: Run full unit test suite**

Run: `make test-run-unit`
Expected: PASS

- [ ] **Step 2: Run PHPStan**

Run: `make lint-phpstan`
Expected: PASS

- [ ] **Step 3: Run PHP-CS-Fixer check**

Run: `make lint-cs-fixer`
Expected: PASS

- [ ] **Step 4: Run Rector check**

Run: `make lint-rector`
Expected: PASS (or no new issues)

---

## Summary of Changes

| Problem | Fix |
|---|---|
| ScrapeConfig generates unused timestamped file on resume | `outputFile` is now `?string`, never auto-generated |
| Double ScrapeConfig creation | Single-pass resolve in Command — one config created |
| Wrong verbose output | Verbose output moved after outputFile is resolved |
| No output-dir validation on resume | `ScrapeStateManager::resume()` throws on mismatch |
| State stores basename, reconstructs path | State stores full path in `output_file` |
| Mixed responsibilities in resolveStartContext | Split into `resolveResumeContext()` + `getPageRange()` |
