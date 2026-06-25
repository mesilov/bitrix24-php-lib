<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerReadModel;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Delete\Command as DeleteCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Delete\Handler as DeleteHandler;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerCreatedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerDeletedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerExternalIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerLogoUrlChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerOpenLineIdChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerPhoneChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerSiteChangedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Events\Bitrix24PartnerTitleChangedEvent;
use League\Csv\Reader;
use League\Csv\Statement;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ImportWorkflow
{
    private const array LISTENER_MAP = [
        Bitrix24PartnerCreatedEvent::class => 'onPartnerCreated',
        Bitrix24PartnerTitleChangedEvent::class => 'onPartnerTitleChanged',
        Bitrix24PartnerSiteChangedEvent::class => 'onPartnerSiteChanged',
        Bitrix24PartnerPhoneChangedEvent::class => 'onPartnerPhoneChanged',
        Bitrix24PartnerEmailChangedEvent::class => 'onPartnerEmailChanged',
        Bitrix24PartnerOpenLineIdChangedEvent::class => 'onPartnerOpenLineIdChanged',
        Bitrix24PartnerExternalIdChangedEvent::class => 'onPartnerExternalIdChanged',
        Bitrix24PartnerLogoUrlChangedEvent::class => 'onPartnerLogoUrlChanged',
        Bitrix24PartnerDeletedEvent::class => 'onPartnerDeleted',
    ];

    /**
     * Прерывает импорт, когда накопится столько ошибок валидации данных.
     * При ~3000 партнёров в реестре 20 ≈ 0.7% — порог системного сбоя источника,
     * а не нормального шума данных (на текущем датасете реально битых строк < 10).
     */
    private const CIRCUIT_BREAKER_ERROR_THRESHOLD = 20;

    public function __construct(
        private readonly Handler $importHandler,
        private readonly DeleteHandler $deleteHandler,
        private readonly Bitrix24PartnerReadModel $readModel,
        private readonly PhoneNumberUtil $phoneUtil,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param null|\Closure(string, int): void $onProgress
     * @param null|\Closure(string): void      $onVerbose
     */
    public function run(ImportConfig $config, ?\Closure $onProgress = null, ?\Closure $onVerbose = null): ImportResult
    {
        $csvMap = $this->readCsv($config, $onProgress, $onVerbose);
        if ([] === $csvMap) {
            return new ImportResult(0, 0, 0, 0, 0, $config->dryRun);
        }

        $collector = new ImportStats();
        $this->registerListeners($collector);

        try {
            $dbMap = $this->loadDbMap($onVerbose);

            if (SyncMode::Full === $config->syncMode) {
                $deleteCount = count(array_diff(array_keys($dbMap), array_keys($csvMap)));
                $onProgress?->__invoke('delete_total', $deleteCount);
            }

            if ($config->dryRun) {
                $this->planDryRun($csvMap, $dbMap, $config, $collector, $onProgress, $onVerbose);
            } else {
                $this->executeImport($csvMap, $dbMap, $config, $collector, $onProgress, $onVerbose);
            }

            return $collector->toResult(count($csvMap), $config->dryRun);
        } finally {
            $this->unregisterListeners($collector);
        }
    }

    /**
     * @param array<int, array<string, string>> $csvMap
     * @param array<int, PartnerSyncView>       $dbMap
     */
    private function executeImport(
        array $csvMap,
        array $dbMap,
        ImportConfig $config,
        ImportStats $collector,
        ?\Closure $onProgress = null,
        ?\Closure $onVerbose = null,
    ): void {
        $processed = 0;

        foreach ($csvMap as $partnerNumber => $row) {
            $onProgress?->__invoke('row_advance', 0);
            ++$processed;

            try {
                $command = $this->buildCommand($row);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка в строке партнёра #%d: %s', $partnerNumber, $e->getMessage()));
                ++$collector->errors;
                $collector->errorsDetail[] = ['partnerNumber' => $partnerNumber, 'error' => $e->getMessage()];

                if ($collector->errors >= self::CIRCUIT_BREAKER_ERROR_THRESHOLD) {
                    throw new \RuntimeException(sprintf(
                        'Circuit breaker: импорт прерван — достигнут порог ошибок валидации данных (%d). '
                        .'Обработано %d из %d строк (создано: %d, обновлено: %d, ошибок: %d). '
                        .'Проверьте источник данных и запустите повторно — уже импортированные строки обновятся, недостающие добавятся.',
                        self::CIRCUIT_BREAKER_ERROR_THRESHOLD,
                        $processed,
                        count($csvMap),
                        $collector->created,
                        $collector->updated,
                        $collector->errors,
                    ), 0, $e);
                }

                continue;
            }

            $this->importHandler->handle($command);
            $onVerbose?->__invoke(sprintf('Партнёр #%d: обработан', $partnerNumber));
        }

        if (SyncMode::Full === $config->syncMode) {
            foreach ($dbMap as $partnerNumber => $partner) {
                if (!isset($csvMap[$partnerNumber])) {
                    $onProgress?->__invoke('delete_advance', 0);
                    $this->deleteHandler->handle(new DeleteCommand(
                        $partner->id,
                        'soft-delete: отсутствует в CSV при полной синхронизации'
                    ));
                    $onVerbose?->__invoke(sprintf('Партнёр #%d: удалён', $partnerNumber));
                }
            }
        }
    }

    /**
     * @param array<int, array<string, string>> $csvMap
     * @param array<int, PartnerSyncView>       $dbMap
     */
    private function planDryRun(array $csvMap, array $dbMap, ImportConfig $config, ImportStats $collector, ?\Closure $onProgress = null, ?\Closure $onVerbose = null): void
    {
        foreach ($csvMap as $partnerNumber => $row) {
            $onProgress?->__invoke('row_advance', 0);

            try {
                $command = $this->buildCommand($row);
            } catch (\Throwable $e) {
                ++$collector->errors;
                $onVerbose?->__invoke(sprintf('Партнёр #%d: ошибка — %s', $partnerNumber, $e->getMessage()));

                continue;
            }

            $existingPartner = $dbMap[$partnerNumber] ?? null;

            if (null === $existingPartner) {
                $collector->plannedActions[] = [
                    'action' => 'CREATE',
                    'partnerNumber' => $partnerNumber,
                    'title' => $command->title,
                ];
                ++$collector->created;
                $onVerbose?->__invoke(sprintf('CREATE #%d %s', $partnerNumber, $command->title));
            } elseif ($this->partnerHasChanges($existingPartner, $command)) {
                $collector->plannedActions[] = [
                    'action' => 'UPDATE',
                    'partnerNumber' => $partnerNumber,
                    'title' => $command->title,
                    'details' => $this->diffFields($existingPartner, $command),
                ];
                ++$collector->updated;
                $onVerbose?->__invoke(sprintf('UPDATE #%d %s (%s)', $partnerNumber, $command->title, $this->diffFields($existingPartner, $command)));
            } else {
                ++$collector->skipped;
            }
        }

        if (SyncMode::Full === $config->syncMode) {
            foreach ($dbMap as $partnerNumber => $partner) {
                if (!isset($csvMap[$partnerNumber])) {
                    $onProgress?->__invoke('delete_advance', 0);
                    $collector->plannedActions[] = [
                        'action' => 'SOFT-DELETE',
                        'partnerNumber' => $partnerNumber,
                        'title' => $partner->title,
                    ];
                    $onVerbose?->__invoke(sprintf('SOFT-DELETE #%d %s', $partnerNumber, $partner->title));
                }
            }
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsv(ImportConfig $config, ?\Closure $onProgress = null, ?\Closure $onVerbose = null): array
    {
        $csv = Reader::from($config->file, 'r');
        $csv->setHeaderOffset(0);

        $records = [...(new Statement())->process($csv)];
        $onVerbose?->__invoke(sprintf('Чтение CSV: %d записей', count($records)));

        $csvMap = [];
        foreach ($records as $record) {
            if ([] === array_filter($record)) {
                continue;
            }

            $number = (int) ($record['bitrix24_partner_number'] ?? 0);
            if ($number > 0) {
                $csvMap[$number] = $record;
            }
        }

        $onProgress?->__invoke('csv_total', count($csvMap));

        return $csvMap;
    }

    /**
     * @return array<int, PartnerSyncView>
     */
    private function loadDbMap(?\Closure $onVerbose = null): array
    {
        $rows = $this->readModel->findAllActiveAsArray();

        $onVerbose?->__invoke(sprintf('Загрузка из БД: %d партнёров', count($rows)));

        $dbMap = [];
        foreach ($rows as $row) {
            $view = PartnerSyncView::fromArray($row);
            $dbMap[$view->bitrix24PartnerNumber] = $view;
        }

        return $dbMap;
    }

    /**
     * @param array<string, string> $row
     */
    private function buildCommand(array $row): Command
    {
        $title = trim((string) ($row['title'] ?? ''));
        $bitrix24PartnerNumber = (int) ($row['bitrix24_partner_number'] ?? 0);

        if ('' === $title) {
            throw new \InvalidArgumentException('title is required');
        }

        if ($bitrix24PartnerNumber <= 0) {
            throw new \InvalidArgumentException('bitrix24_partner_number is required');
        }

        $phone = $this->parsePhone($row['phone'] ?? null);

        return new Command(
            title: $title,
            bitrix24PartnerNumber: $bitrix24PartnerNumber,
            site: $this->nullableField($row['site'] ?? null),
            phone: $phone,
            email: $this->nullableField($row['email'] ?? null),
            logoUrl: $this->nullableField($row['logo_url'] ?? null),
        );
    }

    private function partnerHasChanges(PartnerSyncView $partner, Command $command): bool
    {
        if ($partner->title !== $command->title) {
            return true;
        }

        if ($partner->site !== $command->site) {
            return true;
        }

        if ($partner->email !== $command->email) {
            return true;
        }

        return $partner->logoUrl !== $command->logoUrl;
    }

    private function diffFields(PartnerSyncView $partner, Command $command): string
    {
        $diffs = [];
        if ($partner->title !== $command->title) {
            $diffs[] = 'title';
        }

        if ($partner->site !== $command->site) {
            $diffs[] = 'site';
        }

        if ($partner->email !== $command->email) {
            $diffs[] = 'email';
        }

        if ($partner->logoUrl !== $command->logoUrl) {
            $diffs[] = 'logoUrl';
        }

        return implode(', ', $diffs);
    }

    private function registerListeners(ImportStats $collector): void
    {
        foreach (self::LISTENER_MAP as $eventClass => $method) {
            $this->eventDispatcher->addListener($eventClass, [$collector, $method]);
        }
    }

    private function unregisterListeners(ImportStats $collector): void
    {
        foreach (self::LISTENER_MAP as $eventClass => $method) {
            $this->eventDispatcher->removeListener($eventClass, [$collector, $method]);
        }
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
