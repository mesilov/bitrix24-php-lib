<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\UseCase\Import;

use Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine\Bitrix24PartnerRepository;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Delete\Command as DeleteCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Delete\Handler as DeleteHandler;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Upsert\Command as UpsertCommand;
use Bitrix24\Lib\Bitrix24Partners\UseCase\Upsert\Handler as UpsertHandler;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerInterface;
use League\Csv\Reader;
use League\Csv\Statement;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

class ImportWorkflow
{
    public function __construct(
        private readonly UpsertHandler $upsertHandler,
        private readonly DeleteHandler $deleteHandler,
        private readonly Bitrix24PartnerRepository $repository,
        private readonly PhoneNumberUtil $phoneUtil,
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

        $dbMap = $this->loadDbMap($onVerbose);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $plannedActions = [];

        foreach ($csvMap as $partnerNumber => $row) {
            $onProgress?->__invoke('row_advance', 0);

            try {
                $upsertCommand = $this->buildUpsertCommand($row);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf('Ошибка в строке партнёра #%d: %s', $partnerNumber, $e->getMessage()));
                if (!$config->skipErrors) {
                    ++$errors;

                    throw $e;
                }
                ++$errors;
                $onVerbose?->__invoke(sprintf('Партнёр #%d: ошибка — %s', $partnerNumber, $e->getMessage()));

                continue;
            }

            $existingPartner = $dbMap[$partnerNumber] ?? null;

            if (null === $existingPartner) {
                if ($config->dryRun) {
                    $plannedActions[] = [
                        'action' => 'CREATE',
                        'partnerNumber' => $partnerNumber,
                        'title' => $upsertCommand->title,
                    ];
                    $onVerbose?->__invoke(sprintf('CREATE #%d %s', $partnerNumber, $upsertCommand->title));
                } else {
                    $this->upsertHandler->handle($upsertCommand);
                    $onVerbose?->__invoke(sprintf('Партнёр #%d: создан', $partnerNumber));
                }
                ++$created;
            } elseif ($this->partnerMatchesUpsert($existingPartner, $upsertCommand)) {
                ++$skipped;
            } else {
                if ($config->dryRun) {
                    $plannedActions[] = [
                        'action' => 'UPDATE',
                        'partnerNumber' => $partnerNumber,
                        'title' => $upsertCommand->title,
                        'details' => $this->diffFields($existingPartner, $upsertCommand),
                    ];
                    $onVerbose?->__invoke(sprintf('UPDATE #%d %s (%s)', $partnerNumber, $upsertCommand->title, $this->diffFields($existingPartner, $upsertCommand)));
                } else {
                    $this->upsertHandler->handle($upsertCommand);
                    $onVerbose?->__invoke(sprintf('Партнёр #%d: обновлён', $partnerNumber));
                }
                ++$updated;
            }
        }

        $softDeleted = 0;
        if ('full' === $config->syncMode) {
            $softDeleted = $this->handleFullSync($csvMap, $dbMap, $config->dryRun, $plannedActions, $onVerbose);
        }

        return new ImportResult(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            softDeleted: $softDeleted,
            errors: $errors,
            dryRun: $config->dryRun,
            plannedActions: $plannedActions,
        );
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

    private function partnerMatchesUpsert(Bitrix24PartnerInterface $partner, UpsertCommand $command): bool
    {
        return $partner->getTitle() === $command->title
            && $partner->getBitrix24PartnerNumber() === $command->bitrix24PartnerNumber
            && $partner->getSite() === $command->site
            && $partner->getEmail() === $command->email
            && $partner->getOpenLineId() === $command->openLineId
            && $partner->getExternalId() === $command->externalId
            && $partner->getLogoUrl() === $command->logoUrl;
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

    /**
     * @param array<int, array<string, string>>                                                      $csvMap
     * @param array<int, Bitrix24PartnerInterface>                                                   $dbMap
     * @param array<int, array{action: string, partnerNumber: int, title: string, details?: string}> $plannedActions
     */
    private function handleFullSync(array $csvMap, array $dbMap, bool $dryRun, array &$plannedActions, ?\Closure $onVerbose = null): int
    {
        $softDeleted = 0;

        foreach ($dbMap as $partnerNumber => $partner) {
            if (!isset($csvMap[$partnerNumber])) {
                if ($dryRun) {
                    $plannedActions[] = [
                        'action' => 'SOFT-DELETE',
                        'partnerNumber' => $partnerNumber,
                        'title' => $partner->getTitle(),
                    ];
                    $onVerbose?->__invoke(sprintf('SOFT-DELETE #%d %s', $partnerNumber, $partner->getTitle()));
                } else {
                    $this->deleteHandler->handle(new DeleteCommand(
                        $partner->getId(),
                        'soft-delete: отсутствует в CSV при полной синхронизации'
                    ));
                    $onVerbose?->__invoke(sprintf('Партнёр #%d: удалён', $partnerNumber));
                }
                ++$softDeleted;
            }
        }

        return $softDeleted;
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
