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
            function (Crawler $node) use (&$partners) {
                $partnerNumber = (int) $node->attr('data-partner-id');
                if ($partnerNumber === 0) {
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
                $contactsNode->filter('p')->each(function (Crawler $p) use (&$phone) {
                    $b = $p->filter('b');
                    if ($b->count() > 0 && str_contains(trim($b->text()), 'Телефон')) {
                        $fullText = trim($p->text());
                        $label = trim($b->text());
                        $extracted = trim(str_replace($label, '', $fullText));
                        if ($extracted !== '') {
                            $phone = $extracted;
                        }
                    }
                });

                return $phone;
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга phone: %s', $e->getMessage()));
        }

        return '';
    }

    private function extractEmail(Crawler $crawler): string
    {
        try {
            $contactsNode = $crawler->filter('div.bx-partner-detail-description-contacts-content')->first();
            if ($contactsNode->count() > 0) {
                $email = '';
                $contactsNode->filter('a')->each(function (Crawler $a) use (&$email) {
                    $text = trim($a->text());
                    if (str_contains($text, '@')) {
                        $email = $text;
                    }
                });

                return $email;
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга email: %s', $e->getMessage()));
        }

        return '';
    }

    private function extractLogoUrl(Crawler $crawler): string
    {
        try {
            $logoNode = $crawler->filter('img.bx-partner-detail-header-logo-img')->first();
            if ($logoNode->count() > 0) {
                return $logoNode->attr('src') ?? '';
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга logo_url: %s', $e->getMessage()));
        }

        return '';
    }

    private function extractSite(Crawler $crawler): string
    {
        try {
            $siteNode = $crawler->filter('a.bx-partner-detail-header-info-link')->first();
            if ($siteNode->count() > 0) {
                return $siteNode->attr('href') ?? '';
            }
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка парсинга site: %s', $e->getMessage()));
        }

        return '';
    }
}
