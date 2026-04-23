<?php

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/*#[AsCommand(
    name: 'bitrix24:partners:scrape',
    description: 'Scrape partners from bitrix24.ru/partners and generate CSV file'
)]*/
class ScrapePartnersCommand_V2 extends Command
{
    // Конфигурация
    private const BASE_URL = 'https://www.bitrix24.kz/partners/country__22/';
    private const DELAY_BETWEEN_PAGES = 3; // секунды
    private const DELAY_BETWEEN_PARTNERS = 7; // секунд
    private const HTTP_TIMEOUT = 10; // секунд

    // Зависимости
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
            var_dump($allPartnerData);
            exit;
            $parsedData = $this->parseAndFetchLogos($allPartnerData);
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
        $currentPage = 1;

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
        /*        $response = $this->httpClient->request('POST', self::BASE_URL, [
                    'timeout' => self::HTTP_TIMEOUT,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => http_build_query([
                        'ajax' => 'Y',
                        'page_n' => $page,
                    ]),
                ]);*/

        $response = $this->httpClient->request('POST', self::BASE_URL, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Referer' => 'https://www.bitrix24.kz/partners/', // или конкретная страна
                //    'Referer'          => $url,                    // очень важно, если есть защита по referrer
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

    private function parseAndFetchLogos(array $allPages): array
    {
        $allPartnerData = [];

        foreach ($allPages as $pageData) {
            $partners = $this->parsePartnerHtml($pageData['html']);

            foreach ($partners as $partner) {
                $partnerUrl = $this->extractPartnerUrl($partner);
                $logoUrl = $this->fetchPartnerLogo($partnerUrl);

                $partnerData = [
                    'name' => $this->extractPartnerName($partner),
                    'description' => $this->extractPartnerDescription($partner),
                    'location' => $this->extractPartnerLocation($partner),
                    'level' => $this->extractPartnerLevel($partner),
                    'logo_url' => $logoUrl,
                    'page_url' => $partnerUrl,
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

    private function extractPartnerDescription(\DOMElement $partner): string
    {
        $descriptionDiv = $partner->getElementsByTagName('div')->item(2);

        return $descriptionDiv ? $descriptionDiv->textContent : '';
    }

    private function extractPartnerLocation(\DOMElement $partner): string
    {
        $locationDiv = $partner->getElementsByTagName('div')->item(3);

        return $locationDiv ? $locationDiv->textContent : '';
    }

    private function extractPartnerLevel(\DOMElement $partner): string
    {
        $levelDiv = $partner->getElementsByTagName('div')->item(1);

        return $levelDiv ? $levelDiv->textContent : '';
    }

    private function fetchPartnerLogo(string $partnerUrl): ?string
    {
        if (empty($partnerUrl)) {
            return null;
        }

        try {
            $fullUrl = 'https://www.bitrix24.com'.$partnerUrl;
            $response = $this->httpClient->request('GET', $fullUrl, [
                'timeout' => self::HTTP_TIMEOUT,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);

            $content = $response->getContent();
            $dom = new \DOMDocument();
            @$dom->loadHTML($content);

            $logoImg = $dom->getElementById('bx-page-logo');

            return $logoImg ? $logoImg->getAttribute('src') : null;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Ошибка при получении логотипа: %s', $e->getMessage()));

            return null;
        }
    }

    private function saveToCsv(array $data, string $filename): void
    {
        $handle = fopen($filename, 'w');
        fputcsv($handle, ['Name', 'Description', 'Location', 'Level', 'Logo URL', 'Page URL']);

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
