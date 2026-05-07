<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

readonly class PartnerHtmlParser
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<int, array{partner_number: int, title: string, detail_page_url: string, phone: string}>
     */
    public function parsePartnerListPage(string $html): array
    {
        $crawler = new Crawler($html);
        $partners = [];

        $crawler->filter('div.bp-partner-list-item-cnr.js-partners-list-item')->each(
            function (Crawler $node) use (&$partners): void {
                $partnerNumber = (int) $node->attr('data-partner-id');
                if (0 === $partnerNumber) {
                    return;
                }

                $title = '';
                $detailPageUrl = '';
                $nameLink = $node->filter('a.bp-partner-list-item-name')->first();
                if ($nameLink->count() > 0) {
                    $title = trim($nameLink->text());
                    $detailPageUrl = $nameLink->attr('href') ?? '';
                }

                $phone = '';
                $phoneNode = $node->filter('div.bp-partner-request-phone')->first();
                if ($phoneNode->count() > 0) {
                    $phone = trim($phoneNode->text());
                }

                $partners[$partnerNumber] = [
                    'partner_number' => $partnerNumber,
                    'title' => $title,
                    'detail_page_url' => $detailPageUrl,
                    'phone' => $phone,
                ];
            }
        );

        return $partners;
    }

    /**
     * @return array{phone: string, email: string, logo_url: string, site: string}
     */
    public function parsePartnerDetailPage(string $html): array
    {
        $crawler = new Crawler($html);

        $phone = $this->extractPhone($crawler);
        $email = $this->extractEmail($crawler);
        $logoUrl = $this->extractLogoUrl($crawler);
        $site = $this->extractSite($crawler);

        return [
            'phone' => $phone,
            'email' => $email,
            'logo_url' => $logoUrl,
            'site' => $site,
        ];
    }

    private function extractPhone(Crawler $crawler): string
    {
        try {
            $contactsNode = $crawler->filter('div.bx-partner-detail-description-contacts-content')->first();
            if ($contactsNode->count() > 0) {
                $phone = '';
                $contactsNode->filter('p')->each(function (Crawler $p) use (&$phone): void {
                    $b = $p->filter('b');
                    if ($b->count() > 0 && str_contains(trim($b->text()), 'Телефон')) {
                        $fullText = trim($p->text());
                        $label = trim($b->text());
                        $extracted = trim(str_replace($label, '', $fullText));
                        if ('' !== $extracted) {
                            $phone = $extracted;
                        }
                    }
                });

                return $this->cleanText($phone);
            }
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf('Ошибка парсинга phone: %s', $throwable->getMessage()));
        }

        return '';
    }

    private function extractEmail(Crawler $crawler): string
    {
        try {
            $contactsNode = $crawler->filter('div.bx-partner-detail-description-contacts-content')->first();
            if ($contactsNode->count() > 0) {
                $email = '';
                $contactsNode->filter('a')->each(function (Crawler $a) use (&$email): void {
                    $text = trim($a->text());
                    if (str_contains($text, '@')) {
                        $email = $text;
                    }
                });

                return $this->cleanText($email);
            }
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf('Ошибка парсинга email: %s', $throwable->getMessage()));
        }

        return '';
    }

    private function extractLogoUrl(Crawler $crawler): string
    {
        try {
            $logoNode = $crawler->filter('img.bx-partner-detail-header-logo-img')->first();
            if ($logoNode->count() > 0) {
                return $this->cleanText($logoNode->attr('src') ?? '');
            }
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf('Ошибка парсинга logo_url: %s', $throwable->getMessage()));
        }

        return '';
    }

    private function extractSite(Crawler $crawler): string
    {
        try {
            $siteNode = $crawler->filter('a.bx-partner-detail-header-info-link')->first();
            if ($siteNode->count() > 0) {
                return $this->normalizeUrl($this->cleanText($siteNode->attr('href') ?? ''));
            }
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf('Ошибка парсинга site: %s', $throwable->getMessage()));
        }

        return '';
    }

    private function cleanText(string $text): string
    {
        $text = trim($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string) $text);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ('' === $url) {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return $url;
    }
}
