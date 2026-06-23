<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Partners\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Import\Command as ImportCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Import\Handler as ImportHandler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(Bitrix24PartnerRepository::class)]
class Bitrix24PartnerRepositoryBenchmarkTest extends TestCase
{
    use FunctionalTestTrait;

    private const string CSV_PATH = __DIR__.'/../../../../../var/scraper/partners-20260623-150402.csv';

    private Bitrix24PartnerRepository $repository;

    private ImportHandler $importHandler;

    private PhoneNumberUtil $phoneUtil;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->truncateBitrix24Partners();
        $this->entityManager = EntityManagerFactory::get();
        $this->phoneUtil = PhoneNumberUtil::getInstance();

        $this->repository = new Bitrix24PartnerRepository($this->entityManager);
        $flusher = new Flusher($this->entityManager, new EventDispatcher());
        $this->importHandler = new ImportHandler(
            $this->repository,
            $flusher,
            $this->phoneUtil,
            new NullLogger()
        );
    }

    #[Test]
    public function testBenchmarkFindAllActiveMethods(): void
    {
        $loadedCount = $this->loadCsvData();
        self::assertGreaterThan(0, $loadedCount, 'CSV data must be loaded');

        $this->entityManager->clear();

        $arrayMetrics = $this->benchmarkAsArray($loadedCount);
        $entityMetrics = $this->benchmarkAsEntities($loadedCount);

        $this->printSummary($loadedCount, $arrayMetrics, $entityMetrics);
    }

    /**
     * @return array{time_ms: float, mem_delta_mb: float, peak_delta_mb: float}
     */
    private function benchmarkAsArray(int $expectedCount): array
    {
        $this->entityManager->clear();
        gc_collect_cycles();

        $memBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        $start = microtime(true);

        $result = $this->repository->findAllActiveAsArray();

        $timeMs = (microtime(true) - $start) * 1000;
        $memDelta = (memory_get_usage(true) - $memBefore) / 1048576;
        $peakDelta = (memory_get_peak_usage(true) - $peakBefore) / 1048576;

        self::assertCount($expectedCount, $result);
        self::assertArrayHasKey('id', $result[0]);

        return ['time_ms' => $timeMs, 'mem_delta_mb' => $memDelta, 'peak_delta_mb' => $peakDelta];
    }

    /**
     * @return array{time_ms: float, mem_delta_mb: float, peak_delta_mb: float}
     */
    private function benchmarkAsEntities(int $expectedCount): array
    {
        $this->entityManager->clear();
        gc_collect_cycles();

        $memBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        $start = microtime(true);

        $result = $this->repository->findAllActive();

        $timeMs = (microtime(true) - $start) * 1000;
        $memDelta = (memory_get_usage(true) - $memBefore) / 1048576;
        $peakDelta = (memory_get_peak_usage(true) - $peakBefore) / 1048576;

        self::assertCount($expectedCount, $result);
        self::assertInstanceOf(Bitrix24Partner::class, $result[0]);

        return ['time_ms' => $timeMs, 'mem_delta_mb' => $memDelta, 'peak_delta_mb' => $peakDelta];
    }

    private function loadCsvData(): int
    {
        $handle = fopen(self::CSV_PATH, 'r');
        if (false === $handle) {
            self::fail(sprintf('Cannot open CSV: %s', self::CSV_PATH));
        }

        $headers = fgetcsv($handle);
        if (false === $headers) {
            fclose($handle);
            self::fail('Empty CSV file');
        }

        $created = 0;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $record = array_combine($headers, $row);
            } catch (\ValueError) {
                continue;
            }

            if ([] === array_filter($record)) {
                continue;
            }

            $partnerNumber = (int) ($record['bitrix24_partner_number'] ?? 0);
            if ($partnerNumber <= 0) {
                continue;
            }

            try {
                $command = new ImportCommand(
                    title: trim((string) ($record['title'] ?? '')),
                    bitrix24PartnerNumber: $partnerNumber,
                    site: $this->nullableField($record['site'] ?? null),
                    phone: $this->parsePhone($record['phone'] ?? null),
                    email: $this->nullableField($record['email'] ?? null),
                    logoUrl: $this->nullableField($record['logo_url'] ?? null),
                );
            } catch (\Throwable) {
                continue;
            }

            $this->importHandler->handle($command);
            ++$created;
        }

        fclose($handle);

        return $created;
    }

    /**
     * @param array{time_ms: float, mem_delta_mb: float, peak_delta_mb: float} $arrayMetrics
     * @param array{time_ms: float, mem_delta_mb: float, peak_delta_mb: float} $entityMetrics
     */
    private function printSummary(int $count, array $arrayMetrics, array $entityMetrics): void
    {
        $timeRatio = $arrayMetrics['time_ms'] > 0
            ? $entityMetrics['time_ms'] / $arrayMetrics['time_ms']
            : 0.0;
        $memRatio = $arrayMetrics['mem_delta_mb'] > 0
            ? $entityMetrics['mem_delta_mb'] / $arrayMetrics['mem_delta_mb']
            : 0.0;

        fwrite(STDOUT, sprintf(
            "\n  ┌── Benchmark Results (%d partners)\n"
            ."  │\n"
            ."  │  findAllActiveAsArray()  — plain arrays (no hydration)\n"
            ."  │    Time:    %8.2f ms\n"
            ."  │    Memory:  %8.2f MB\n"
            ."  │    Peak:    %8.2f MB\n"
            ."  │\n"
            ."  │  findAllActive()         — hydrated Bitrix24Partner entities\n"
            ."  │    Time:    %8.2f ms\n"
            ."  │    Memory:  %8.2f MB\n"
            ."  │    Peak:    %8.2f MB\n"
            ."  │\n"
            ."  │  Ratio (entities / array)\n"
            ."  │    Time:    %8.1fx\n"
            ."  │    Memory:  %8.1fx\n"
            ."  └──\n",
            $count,
            $arrayMetrics['time_ms'],
            $arrayMetrics['mem_delta_mb'],
            $arrayMetrics['peak_delta_mb'],
            $entityMetrics['time_ms'],
            $entityMetrics['mem_delta_mb'],
            $entityMetrics['peak_delta_mb'],
            $timeRatio,
            $memRatio,
        ));
    }

    private function parsePhone(?string $phoneString): ?PhoneNumber
    {
        if (null === $phoneString || '' === trim($phoneString)) {
            return null;
        }

        $phoneString = explode(',', $phoneString)[0];

        try {
            return $this->phoneUtil->parse(trim($phoneString), 'RU');
        } catch (NumberParseException) {
            return null;
        }
    }

    private function nullableField(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }
}
