<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use Carbon\CarbonImmutable;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class PartnerPageScraper
{
    private const int HTTP_TIMEOUT = 10;

    private const int BINARY_SEARCH_STEP = 100;

    private ?ClientInterface $httpClient = null;

    private ?RequestFactoryInterface $requestFactory = null;

    private ?StreamFactoryInterface $streamFactory = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PartnerHtmlParser $parser,
    ) {}

    public function findLastPage(string $baseUrl, bool $insecure, SymfonyStyle $io): int
    {
        $io->text('Проверяем страницу 1...');
        if (!$this->hasPartnersOnPage(1, $baseUrl, $insecure)) {
            throw new \RuntimeException('Страница 1 не существует или не содержит партнёров. Проверьте URL и доступность сайта.');
        }

        $io->text('Страница 1 существует');

        $low = 1;
        $high = self::BINARY_SEARCH_STEP;

        while ($this->hasPartnersOnPage($high, $baseUrl, $insecure)) {
            $io->text(sprintf('Страница %d существует, проверяем %d...', $high, $high + self::BINARY_SEARCH_STEP));
            $low = $high;
            $high += self::BINARY_SEARCH_STEP;
            sleep(1);
        }

        $io->text(sprintf('Страница %d не существует, бинарный поиск между %d и %d...', $high, $low, $high));

        while ($low < $high - 1) {
            $mid = (int) ceil(($low + $high) / 2);

            if ($this->hasPartnersOnPage($mid, $baseUrl, $insecure)) {
                $io->text(sprintf('Страница %d существует', $mid));
                $low = $mid;
            } else {
                $io->text(sprintf('Страница %d не существует', $mid));
                $high = $mid;
            }

            sleep(1);
        }

        return $low;
    }

    public function fetchPageHtml(int $pageNumber, string $baseUrl, bool $insecure): ?string
    {
        $baseDomain = $this->extractBaseDomain($baseUrl);
        $request = $this->getRequestFactory()->createRequest('POST', $baseUrl)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Referer', $baseDomain.'/partners/')
        ;

        $body = $this->getStreamFactory()->createStream(
            http_build_query(['ajax' => 'Y', 'page_n' => $pageNumber])
        );
        $request = $request->withBody($body);

        $response = $this->getHttpClient($insecure)->sendRequest($request);
        $content = $response->getBody()->getContents();

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['html'])) {
            return null;
        }

        return $data['html'];
    }

    /**
     * @return array<int, array{partner_number: int, title: string, detail_page_url: string, phone: string}>
     */
    public function fetchPartnerList(int $pageNumber, string $baseUrl, bool $insecure): array
    {
        $html = $this->fetchPageHtml($pageNumber, $baseUrl, $insecure);
        if (null === $html) {
            return [];
        }

        return $this->parser->parsePartnerListPage($html);
    }

    public function fetchPartnerData(int $partnerId, string $baseDomain, bool $insecure = false): ?PartnerData
    {
        $detailPageUrl = '/partners/partner/'.$partnerId.'/';

        $html = $this->fetchPartnerDetailHtml($detailPageUrl, $insecure, $baseDomain);
        if (null === $html) {
            return null;
        }

        $detail = $this->parser->parsePartnerDetailPage($html);
        $title = $this->extractTitleFromHtml($html);

        return new PartnerData(
            bitrix24PartnerNumber: $partnerId,
            title: $title,
            site: '' !== $detail['site'] ? $detail['site'] : null,
            phone: '' !== $detail['phone'] ? $detail['phone'] : null,
            email: '' !== $detail['email'] ? $detail['email'] : null,
            logoUrl: '' !== $detail['logo_url'] ? $detail['logo_url'] : null,
            detailPageUrl: $detailPageUrl,
            baseDomain: $baseDomain,
            scrapedAt: CarbonImmutable::now(),
        );
    }

    private function fetchPartnerDetailHtml(string $detailPageUrl, bool $insecure, string $baseDomain): ?string
    {
        if ('' === $detailPageUrl) {
            return null;
        }

        $fullUrl = rtrim($baseDomain, '/').$detailPageUrl;

        try {
            $request = $this->getRequestFactory()->createRequest('GET', $fullUrl)
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeader('Referer', $baseDomain.'/partners/')
            ;

            $response = $this->getHttpClient($insecure)->sendRequest($request);

            return $response->getBody()->getContents();
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf('Ошибка при загрузке детальной страницы %s: %s', $fullUrl, $throwable->getMessage()));

            return null;
        }
    }

    private function hasPartnersOnPage(int $pageNumber, string $baseUrl, bool $insecure): bool
    {
        $html = $this->fetchPageHtml($pageNumber, $baseUrl, $insecure);
        if (null === $html) {
            return false;
        }

        $crawler = new Crawler($html);

        return $crawler->filter('div.bp-partner-list-item-cnr.js-partners-list-item')->count() > 0;
    }

    private function getHttpClient(bool $insecure = false): ClientInterface
    {
        if ($insecure && null === $this->httpClient) {
            $symfonyClient = HttpClient::create([
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => self::HTTP_TIMEOUT,
            ]);
            $this->httpClient = new Psr18Client($symfonyClient);

            return $this->httpClient;
        }

        if (null === $this->httpClient) {
            $this->httpClient = Psr18ClientDiscovery::find();
        }

        return $this->httpClient;
    }

    private function getRequestFactory(): RequestFactoryInterface
    {
        if (null === $this->requestFactory) {
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        }

        return $this->requestFactory;
    }

    private function getStreamFactory(): StreamFactoryInterface
    {
        if (null === $this->streamFactory) {
            $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
        }

        return $this->streamFactory;
    }

    private function extractBaseDomain(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];

        return $scheme.'://'.$host;
    }

    private function extractTitleFromHtml(string $html): string
    {
        try {
            $crawler = new Crawler($html);
            $titleNode = $crawler->filter('h1.bx-partner-detail-header-title')->first();
            if ($titleNode->count() > 0) {
                return trim($titleNode->text());
            }
        } catch (\Throwable $throwable) {
            $this->logger->warning(sprintf('Ошибка парсинга title: %s', $throwable->getMessage()));
        }

        return '';
    }
}
