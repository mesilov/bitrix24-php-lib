<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bitrix24:partners:scrape',
    description: 'Scrape partners from bitrix24.ru/partners and generate CSV file'
)]
class ScrapePartnersCommand extends Command
{
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
                'https://www.bitrix24.ru/partners/'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getOption('url');
        $outputFile = $input->getOption('output');

        $io->title('Scraping Bitrix24 Partners');
        $io->info(sprintf('Fetching partners from: %s', $url));

        try {
            // Fetch HTML content
            $html = $this->fetchUrl($url);

            // Parse partners from HTML
            $partners = $this->parsePartners($html);

            if (empty($partners)) {
                $io->warning('No partners found');

                return Command::FAILURE;
            }

            // Save to CSV
            $this->saveToCsv($partners, $outputFile);

            $io->success(sprintf('Successfully scraped %d partners and saved to %s', count($partners), $outputFile));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function fetchUrl(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $html || 200 !== $httpCode) {
            throw new \RuntimeException(sprintf('Failed to fetch URL: HTTP %d', $httpCode));
        }

        return $html;
    }

    /**
     * @return array<array<string, string>>
     */
    private function parsePartners(string $html): array
    {
        $partners = [];

        // Create DOMDocument to parse HTML
        $dom = new \DOMDocument();
        // Suppress warnings from malformed HTML
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new \DOMXPath($dom);

        // Try to find partner cards/blocks
        // This is a generic pattern - may need adjustment based on actual HTML structure
        $partnerNodes = $xpath->query("//*[contains(@class, 'partner')]");

        if (0 === $partnerNodes->length) {
            // Try alternative selectors
            $partnerNodes = $xpath->query("//article|//div[contains(@class, 'card')]");
        }

        foreach ($partnerNodes as $node) {
            $partner = [
                'title' => '',
                'site' => '',
                'phone' => '',
                'email' => '',
            ];

            // Extract title
            $titleNode = $xpath->query(".//*[contains(@class, 'title')]|.//h1|.//h2|.//h3", $node);
            if ($titleNode->length > 0) {
                $partner['title'] = trim($titleNode->item(0)->textContent);
            }

            // Extract website
            $linkNode = $xpath->query(".//a[contains(@href, 'http')]", $node);
            if ($linkNode->length > 0) {
                $href = $linkNode->item(0)->getAttribute('href');
                if (!empty($href) && str_contains($href, 'http')) {
                    $partner['site'] = $href;
                }
            }

            // Extract email
            $emailNode = $xpath->query(".//a[contains(@href, 'mailto:')]", $node);
            if ($emailNode->length > 0) {
                $email = str_replace('mailto:', '', $emailNode->item(0)->getAttribute('href'));
                $partner['email'] = $email;
            }

            // Extract phone
            $phoneNode = $xpath->query(".//a[contains(@href, 'tel:')]", $node);
            if ($phoneNode->length > 0) {
                $phone = str_replace('tel:', '', $phoneNode->item(0)->getAttribute('href'));
                $partner['phone'] = $phone;
            }

            // Only add if we have at least a title
            if (!empty($partner['title'])) {
                $partners[] = $partner;
            }
        }

        return $partners;
    }

    /**
     * @param array<array<string, string>> $partners
     */
    private function saveToCsv(array $partners, string $filename): void
    {
        $fp = fopen($filename, 'w');
        if (false === $fp) {
            throw new \RuntimeException(sprintf('Cannot open file: %s', $filename));
        }

        // Write header
        fputcsv($fp, ['title', 'site', 'phone', 'email', 'bitrix24_partner_id', 'open_line_id', 'external_id']);

        // Write data
        foreach ($partners as $partner) {
            fputcsv($fp, [
                $partner['title'] ?? '',
                $partner['site'] ?? '',
                $partner['phone'] ?? '',
                $partner['email'] ?? '',
                '', // bitrix24_partner_id
                '', // open_line_id
                '', // external_id
            ]);
        }

        fclose($fp);
    }
}
