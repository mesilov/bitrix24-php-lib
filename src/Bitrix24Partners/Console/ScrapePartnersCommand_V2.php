<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
#[AsCommand(
    name: 'bitrix24:partners:scrape:v2',
    description: 'Scrape partners from bitrix24.ru/partners and generate CSV file'
)]
class ScrapePartnersCommand_V2 extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output CSV file path',
                'partners.csv'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_OPTIONAL,
                'URL to scrape partners from',
                'https://www.bitrix24.kz/partners/'
            )
            ->addOption(
                'ajax_url',
                'ajax_u',
                InputOption::VALUE_OPTIONAL,
                'URL to scrape partners from',
                'https://www.bitrix24.kz/partners/country__22/'
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $url = $input->getOption('url');
        $ajaxUrl = $input->getOption('ajax_url');
        $outputFile = $input->getOption('output');

        $symfonyStyle->title('Scraping Bitrix24 Partners');
        $symfonyStyle->info(sprintf('Fetching partners from: %s', $url));

        try {
            // Fetch HTML content
           // $html = $this->fetchUrl($url);
           // var_dump($html);
              $htmlAjax = $this->fetchUrlAjax($ajaxUrl);
            var_dump($htmlAjax);
            exit();
            // Parse partners from HTML
            $partners = $this->parsePartners($html);

            if ([] === $partners) {
                $symfonyStyle->warning('No partners found');

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $symfonyStyle->error(sprintf('Error: %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }

    private function fetchUrl(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
            'max_redirects' => 5,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            throw new \RuntimeException(sprintf('Failed to fetch URL: HTTP %d', $statusCode));
        }

        return $response->getContent();
    }

    private function fetchUrlAjax(string $url): string
    {
        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/x-www-form-urlencoded',
                'Referer'          => 'https://www.bitrix24.kz/partners/', // или конкретная страна
            //    'Referer'          => $url,                    // очень важно, если есть защита по referrer
            ],
            'max_redirects' => 5,
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => 5,
            'body' => http_build_query([
                'ajax' => 'Y',
                'page_n' => 3
            ])
        ]);

        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            throw new \RuntimeException(sprintf('Failed to fetch URL: HTTP %d', $statusCode));
        }

        return $response->getContent();
    }

    /**
     * @return array<array<string, string>>
     */
    private function parsePartners(string $html): array
    {

    }
}
