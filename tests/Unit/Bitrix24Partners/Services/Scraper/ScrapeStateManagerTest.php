<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\Bitrix24Partners\Services\Scraper;

use Bitrix24\Lib\Bitrix24Partners\Services\Scraper\ScrapeStateManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScrapeStateManager::class)]
class ScrapeStateManagerTest extends TestCase
{
    private string $tmpDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/scrape-state-test-'.uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->tmpDir.'/state.json');
        // Clean up any CSV files we created
        foreach (glob($this->tmpDir.'/*.csv') as $file) {
            @unlink($file);
        }

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
            partnersPerPage: 12,
            zone: 'ru',
        );

        $stateJson = file_get_contents($this->tmpDir.'/state.json');
        $this->assertNotFalse($stateJson);
        $state = json_decode($stateJson, true);

        $this->assertSame($this->tmpDir, $state['output_dir']);
        $this->assertSame($this->tmpDir.'/partners-20260526-120000.csv', $state['output_file']);
        $this->assertSame(42, $state['total_pages']);
        $this->assertSame(0, $state['last_completed_page']);
        $this->assertSame(12, $state['partners_per_page']);
        $this->assertSame('ru', $state['zone']);
    }

    #[Test]
    public function updateProgressUpdatesCompletedPage(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-test.csv', 'https://example.com', 10, 12, 'ru');

        $manager->updateProgress($this->tmpDir, 5);

        $stateJson = file_get_contents($this->tmpDir.'/state.json');
        $state = json_decode($stateJson, true);
        $this->assertSame(5, $state['last_completed_page']);
    }

    #[Test]
    public function resumeReturnsNullWhenNoStateFile(): void
    {
        $manager = new ScrapeStateManager();

        $result = $manager->resume($this->tmpDir, 'ru');

        $this->assertNull($result);
    }

    #[Test]
    public function resumeReturnsContextFromState(): void
    {
        $manager = new ScrapeStateManager();
        $outputFile = $this->tmpDir.'/partners-test.csv';
        file_put_contents($outputFile, "bitrix24_partner_number,title\n");

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20, 12, 'ru');
        $manager->updateProgress($this->tmpDir, 9);

        $result = $manager->resume($this->tmpDir, 'ru');

        $this->assertSame(10, $result['startPage']);
        $this->assertSame(20, $result['lastPage']);
        $this->assertSame(12, $result['partnersPerPage']);
        $this->assertSame($outputFile, $result['outputFile']);
        $this->assertIsArray($result['processedNumbers']);
    }

    #[Test]
    public function resumeThrowsWhenOutputDirMismatches(): void
    {
        $manager = new ScrapeStateManager();
        $outputFile = $this->tmpDir.'/partners-test.csv';
        file_put_contents($outputFile, "bitrix24_partner_number,title\n");

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20, 12, 'ru');

        $otherDir = sys_get_temp_dir().'/scrape-state-test-other-'.uniqid();
        mkdir($otherDir, 0755, true);
        copy($this->tmpDir.'/state.json', $otherDir.'/state.json');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/output-dir не совпадает/');
            $manager->resume($otherDir, 'ru');
        } finally {
            @unlink($otherDir.'/state.json');
            @rmdir($otherDir);
        }
    }

    #[Test]
    public function resumeThrowsWhenZoneMismatches(): void
    {
        $manager = new ScrapeStateManager();
        $outputFile = $this->tmpDir.'/partners-test.csv';
        file_put_contents($outputFile, "bitrix24_partner_number,title\n");

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20, 12, 'ru');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/zone не совпадает/');
        $manager->resume($this->tmpDir, 'kz');
    }

    #[Test]
    public function completeRemovesStateFile(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-test.csv', 'https://example.com', 10, 12, 'ru');

        $this->assertFileExists($this->tmpDir.'/state.json');

        $manager->complete($this->tmpDir);

        $this->assertFileDoesNotExist($this->tmpDir.'/state.json');
    }

    #[Test]
    public function initStateOverwritesExistingState(): void
    {
        $manager = new ScrapeStateManager();
        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-old.csv', 'https://example.com', 10, 12, 'ru');

        $manager->initState($this->tmpDir, $this->tmpDir.'/partners-new.csv', 'https://example.com', 30, 12, 'ru');

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

        $manager->initState($this->tmpDir, $outputFile, 'https://example.com', 20, 12, 'ru');
        $manager->updateProgress($this->tmpDir, 5);

        $result = $manager->resume($this->tmpDir, 'ru');

        $this->assertArrayHasKey(101, $result['processedNumbers']);
        $this->assertArrayHasKey(202, $result['processedNumbers']);
        $this->assertTrue($result['processedNumbers'][101]);
        $this->assertTrue($result['processedNumbers'][202]);
    }
}
