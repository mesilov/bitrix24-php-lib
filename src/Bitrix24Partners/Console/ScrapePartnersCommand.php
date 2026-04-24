<?php

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use DOMDocument;
use DOMElement;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;

/*#[AsCommand(
    name: 'bitrix24:partners:scrape',
    description: 'Scrape partners from bitrix24.ru/partners and generate CSV file'
)]*/
class ScrapePartnersCommand extends Command
{
    // Конфигурация
    private const BASE_URL = 'https://www.bitrix24.kz/partners/country__22/';
    private const DELAY_BETWEEN_PAGES = 3; // секунды
    private const DELAY_BETWEEN_PARTNERS = 3; // секунд
    private const HTTP_TIMEOUT = 10; // секунд
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private OutputInterface $output;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->setName('partners:scrape')
            ->setDescription('Парсит партнеров Bitrix24 и сохраняет данные в CSV')
            ->addArgument('output-file', InputArgument::OPTIONAL, 'Путь к выходному CSV файлу', 'partners_data.csv')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $this->output = $output;
        $symfonyStyle->info('Начало парсинга партнеров Bitrix24...');

        try {
            $allPartnerData = $this->fetchAllPartnerPages();
            $parsedData = $this->parseAndFetchPartnerDetail($allPartnerData);
            $this->saveToCsv($parsedData, 'partners_data.csv');
            $output->writeln('Парсинг завершен успешно!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при парсинге: '.$e->getMessage());
            $output->writeln('Ошибка: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function fetchAllPartnerPages(): array
    {
        $allPages = [];
        $currentPage = 17;

        while (true) {
            $this->output->writeln(sprintf('Получение страницы %d: %s', $currentPage, self::BASE_URL));

            try {
                $response = $this->fetchPartnerList($currentPage);

                if (empty($response['html'])) {
                    break;
                }

                $allPages[] = $response;
                ++$currentPage;
                sleep(self::DELAY_BETWEEN_PAGES);
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Ошибка при получении страницы %d: %s', $currentPage, $e->getMessage()));

                throw $e;
            }
        }

        return $allPages;
    }

    private function fetchPartnerList(int $page): array
    {
        $response = $this->httpClient->request('POST', self::BASE_URL, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Referer' => 'https://www.bitrix24.kz/partners/', // или конкретная страна
            ],
            'max_redirects' => 5,
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => self::HTTP_TIMEOUT,
            'body' => http_build_query([
                'ajax' => 'Y',
                'page_n' => $page,
            ]),
        ]);

        $content = $response->getContent();

        return json_decode($content, true);
    }

    private function parseAndFetchPartnerDetail(array $allPages): array
    {
        $allPartnerData = [];
        foreach ($allPages as $pageData) {
            $partners = $this->parsePartnerHtml($pageData['html']);

            foreach ($partners as $partner) {
                $partnerUrl = $this->extractPartnerUrl($partner);
                $partnerPageData = $this->fetchPartnerDetail($partnerUrl);
                $partnerDetail = $this->parsePartnerHtmlDetail($partnerPageData);

                $partnerData = [
                    'title' => $this->extractPartnerName($partner),
                    'site' => $this->extractPartnerSite($partnerDetail),
                    'phone' => $this->extractPartnerPhoneNumber($partner),
                    'email' => $this->extractPartnerEmail($partnerDetail),
                    'bitrix24_partner_number' => $this->extractPartnerNumber($partner),
                    'open_line_id' => '',
                    'external_id' => '',
                    'logo_url' => $this->extractPartnerLogoUrl($partnerDetail),
                ];

                $allPartnerData[] = $partnerData;
                sleep(self::DELAY_BETWEEN_PARTNERS);
            }
        }

        return $allPartnerData;
    }

    private function parsePartnerHtml(string $html): array
    {
        // Используем DOMDocument для парсинга HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        $partners = [];
        $items = $dom->getElementsByTagName('div');

        foreach ($items as $item) {
            if ('bp-partner-list-item-cnr js-partners-list-item' === $item->getAttribute('class')) {
                $partners[] = $item;
            }
        }

        return $partners;
    }

    private function parsePartnerHtmlDetail(string $partnerDetail): ?DOMElement
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($partnerDetail);

        $items = $dom->getElementsByTagName('div');

        foreach ($items as $item) {
            if ('bx-pdl-main' === $item->getAttribute('class')) {
                return $item;
            }
        }

        return null;
    }

    private function extractPartnerLogoUrl(\DOMElement $partner): string
    {
        $items = $partner->getElementsByTagName('img');

        $logoUrl = null;

        if ($items->count() > 0) {
            foreach ($items as $item) {
                if ('bx-partner-detail-header-logo-img' === $item->getAttribute('class')) {
                    $logoUrl = $item->getAttribute('src');
                }
            }
        }

        return $logoUrl;
    }

    private function extractPartnerUrl(\DOMElement $partner): string
    {
        $link = $partner->getElementsByTagName('a')->item(0);

        return $link ? $link->getAttribute('href') : '';
    }

    private function extractPartnerName(\DOMElement $partner): string
    {
        $link = $partner->getElementsByTagName('a')->item(0);

        return $link ? $link->textContent : '';
    }

    private function extractPartnerSite(\DOMElement $partner): string
    {
        $site = null;

        $items = $partner->getElementsByTagName('a');
        foreach ($items as $item) {
            if ('bx-partner-detail-header-info-link' == $item->getAttribute('class')) {
                $site = $item->getAttribute('href');
                break;
            }
        }

        return $site;
    }

    private function extractPartnerEmail(\DOMElement $partner): string
    {
        $email = null;

        $items = $partner->getElementsByTagName('div');
        foreach ($items as $item) {
            if ('bx-partner-detail-description-contacts-content js-contancts-content' == $item->getAttribute('class')) {
                $itemsLink = $item->getElementsByTagName('a');
                foreach ($itemsLink as $itemLink) {
                    $email = trim($itemLink->textContent);
                }
                break;
            }
        }

        return $email;
    }

    private function extractPartnerNumber(\DOMElement $partner): ?int
    {
        $number = (int)$partner->getAttribute('data-partner-id');
        return $number ?: null;
    }

    private function extractPartnerPhoneNumber(\DOMElement $partnerDetail): ?string
    {
        $phoneNumber = null;

        $items = $partnerDetail->getElementsByTagName('div');
        foreach ($items as $item) {
            if ('bp-partner-request-phone' == $item->getAttribute('class')) {
                $phoneNumber = $item->textContent;
                break;
            }
        }

        return $phoneNumber;
    }

    private function fetchPartnerDetail(string $partnerUrl): ?string
    {
        if (empty($partnerUrl)) {
            return null;
        }

        try {
            $fullUrl = 'https://www.bitrix24.kz'.$partnerUrl;
            $response = $this->httpClient->request('GET', $fullUrl, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Referer' => 'https://www.bitrix24.kz/partners/', // или конкретная страна
                ],
                'max_redirects' => 5,
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => self::HTTP_TIMEOUT,
            ]);

            return $response->getContent();

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Ошибка при получении логотипа: %s', $e->getMessage()));
            return null;
        }
    }

    private function saveToCsv(array $data, string $filename): void
    {
        $handle = fopen($filename, 'w');
        fputcsv($handle, ['title', 'site', 'phone', 'email', 'bitrix24_partner_number', 'open_line_id','external_id','logo_url']);

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
