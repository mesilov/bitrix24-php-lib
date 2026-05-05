<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use League\Csv\Reader;
use League\Csv\Writer;

class PartnerCsvStorage
{
    public const CSV_HEADERS = [
        'bitrix24_partner_number',
        'title',
        'site',
        'phone',
        'email',
        'logo_url',
        'detail_page_url',
        'scraped_at',
    ];

    public function createWriter(string $filePath): Writer
    {
        $writer = Writer::from($filePath, 'w+');
        $writer->insertOne(self::CSV_HEADERS);

        return $writer;
    }

    public function createWriterForResume(string $filePath): Writer
    {
        return Writer::from($filePath, 'a+');
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function readAsPartnerMap(string $filePath): array
    {
        $reader = Reader::from($filePath);
        $reader->setHeaderOffset(0);

        $records = [];
        foreach ($reader->getRecords() as $record) {
            $number = (int) ($record['bitrix24_partner_number'] ?? 0);
            if ($number > 0) {
                $records[$number] = $record;
            }
        }

        return $records;
    }

    /**
     * @param array{partner_number: int, title: string, site: string, phone: string, email: string, logo_url: string, detail_page_url: string} $partner
     */
    public function writePartner(Writer $writer, array $partner): void
    {
        $writer->insertOne([
            $partner['partner_number'],
            $partner['title'],
            $partner['site'],
            $partner['phone'],
            $partner['email'],
            $partner['logo_url'],
            $partner['detail_page_url'],
            (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @param array<int, array<string, string>> $partnerMap
     */
    public function writeAll(string $filePath, array $partnerMap): void
    {
        $writer = Writer::from($filePath, 'w+');
        $writer->insertOne(self::CSV_HEADERS);
        foreach ($partnerMap as $record) {
            $writer->insertOne(array_values($record));
        }
    }
}
