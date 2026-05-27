<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Delete\Command as DeleteCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Delete\Handler as DeleteHandler;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Upsert\Command as UpsertCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Upsert\Handler as UpsertHandler;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
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

    public function __construct(
        private readonly UpsertHandler $upsertHandler,
        private readonly DeleteHandler $deleteHandler,
        private readonly Bitrix24PartnerRepository $repository,
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
     * @param array<int, array<string, string>>    $csvMap
     * @param array<int, Bitrix24PartnerInterface> $dbMap
     */
    private function executeImport(
        array $csvMap,
        array $dbMap,
        ImportConfig $config,
        ImportStats $collector,
        ?\Closure $onProgress = null,
        ?\Closure $onVerbose = null,
    ): void {
        foreach ($csvMap as $partnerNumber => $row) {
            $onProgress?->__invoke('row_advance', 0);

            try {
                $upsertCommand = $this->buildUpsertCommand($row);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка в строке партнёра #%d: %s', $partnerNumber, $e->getMessage()));
                ++$collector->errors;

                if (!$config->skipErrors) {
                    throw $e;
                }

                $onVerbose?->__invoke(sprintf('Партнёр #%d: ошибка — %s', $partnerNumber, $e->getMessage()));

                continue;
            }

            $this->upsertHandler->handle($upsertCommand);
            $onVerbose?->__invoke(sprintf('Партнёр #%d: обработан', $partnerNumber));
        }

        if (SyncMode::Full === $config->syncMode) {
            foreach ($dbMap as $partnerNumber => $partner) {
                if (!isset($csvMap[$partnerNumber])) {
                    $onProgress?->__invoke('delete_advance', 0);
                    $this->deleteHandler->handle(new DeleteCommand(
                        $partner->getId(),
                        'soft-delete: отсутствует в CSV при полной синхронизации'
                    ));
                    $onVerbose?->__invoke(sprintf('Партнёр #%d: удалён', $partnerNumber));
                }
            }
        }
    }

    /**
     * @param array<int, array<string, string>>    $csvMap
     * @param array<int, Bitrix24PartnerInterface> $dbMap
     */
    private function planDryRun(array $csvMap, array $dbMap, ImportConfig $config, ImportStats $collector, ?\Closure $onProgress = null, ?\Closure $onVerbose = null): void
    {
        foreach ($csvMap as $partnerNumber => $row) {
            $onProgress?->__invoke('row_advance', 0);

            try {
                $upsertCommand = $this->buildUpsertCommand($row);
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
                    'title' => $upsertCommand->title,
                ];
                ++$collector->created;
                $onVerbose?->__invoke(sprintf('CREATE #%d %s', $partnerNumber, $upsertCommand->title));
            } elseif ($this->partnerHasChanges($existingPartner, $upsertCommand)) {
                $collector->plannedActions[] = [
                    'action' => 'UPDATE',
                    'partnerNumber' => $partnerNumber,
                    'title' => $upsertCommand->title,
                    'details' => $this->diffFields($existingPartner, $upsertCommand),
                ];
                ++$collector->updated;
                $onVerbose?->__invoke(sprintf('UPDATE #%d %s (%s)', $partnerNumber, $upsertCommand->title, $this->diffFields($existingPartner, $upsertCommand)));
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
                        'title' => $partner->getTitle(),
                    ];
                    $onVerbose?->__invoke(sprintf('SOFT-DELETE #%d %s', $partnerNumber, $partner->getTitle()));
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
     * @return array<int, Bitrix24PartnerInterface>
     */
    private function loadDbMap(?\Closure $onVerbose = null): array
    {
        $partners = $this->repository->findAllActive();
        $onVerbose?->__invoke(sprintf('Загрузка из БД: %d партнёров', count($partners)));

        $dbMap = [];
        foreach ($partners as $partner) {
            $dbMap[$partner->getBitrix24PartnerNumber()] = $partner;
        }

        return $dbMap;
    }

    /**
     * @param array<string, string> $row
     */
    private function buildUpsertCommand(array $row): UpsertCommand
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

        return new UpsertCommand(
            title: $title,
            bitrix24PartnerNumber: $bitrix24PartnerNumber,
            site: $this->nullableField($row['site'] ?? null),
            phone: $phone,
            email: $this->nullableField($row['email'] ?? null),
            openLineId: $this->nullableField($row['open_line_id'] ?? null),
            externalId: $this->nullableField($row['external_id'] ?? null),
            logoUrl: $this->nullableField($row['logo_url'] ?? null),
        );
    }

    private function partnerHasChanges(Bitrix24PartnerInterface $partner, UpsertCommand $command): bool
    {
        if ($partner->getTitle() !== $command->title) {
            return true;
        }

        if ($partner->getSite() !== $command->site) {
            return true;
        }

        if ($partner->getEmail() !== $command->email) {
            return true;
        }

        if ($partner->getOpenLineId() !== $command->openLineId) {
            return true;
        }

        if ($partner->getExternalId() !== $command->externalId) {
            return true;
        }

        return $partner->getLogoUrl() !== $command->logoUrl;
    }

    private function diffFields(Bitrix24PartnerInterface $partner, UpsertCommand $command): string
    {
        $diffs = [];
        if ($partner->getTitle() !== $command->title) {
            $diffs[] = 'title';
        }

        if ($partner->getSite() !== $command->site) {
            $diffs[] = 'site';
        }

        if ($partner->getEmail() !== $command->email) {
            $diffs[] = 'email';
        }

        if ($partner->getOpenLineId() !== $command->openLineId) {
            $diffs[] = 'openLineId';
        }

        if ($partner->getExternalId() !== $command->externalId) {
            $diffs[] = 'externalId';
        }

        if ($partner->getLogoUrl() !== $command->logoUrl) {
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
