<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Scraper;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Style\SymfonyStyle;

class PartnerPageScraper
{
    private const HTTP_TIMEOUT = 10;
    private const BINARY_SEARCH_STEP = 100;

    private ?ClientInterface $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;

    public function __construct(
        private readonly LoggerInterface $logger,
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
            ->withHeader('Referer', $baseDomain.'/partners/');

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

    public function fetchPartnerDetailHtml(string $detailPageUrl, bool $insecure, string $baseDomain): ?string
    {
        if ($detailPageUrl === '') {
            return null;
        }

        $fullUrl = rtrim($baseDomain, '/') . $detailPageUrl;

        try {
            $request = $this->getRequestFactory()->createRequest('GET', $fullUrl)
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->withHeader('Referer', $baseDomain . '/partners/');

            $response = $this->getHttpClient($insecure)->sendRequest($request);

            return $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf('Ошибка при загрузке детальной страницы %s: %s', $fullUrl, $e->getMessage()));

            return null;
        }
    }

    private function hasPartnersOnPage(int $pageNumber, string $baseUrl, bool $insecure): bool
    {
        $html = $this->fetchPageHtml($pageNumber, $baseUrl, $insecure);
        if ($html === null) {
            return false;
        }

        $crawler = new Crawler($html);

        return $crawler->filter('div.bp-partner-list-item-cnr.js-partners-list-item')->count() > 0;
    }

    private function getHttpClient(bool $insecure = false): ClientInterface
    {
        if ($insecure && $this->httpClient === null) {
            $symfonyClient = \Symfony\Component\HttpClient\HttpClient::create([
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => self::HTTP_TIMEOUT,
            ]);
            $this->httpClient = new \Symfony\Component\HttpClient\Psr18Client($symfonyClient);

            return $this->httpClient;
        }

        if ($this->httpClient === null) {
            $this->httpClient = Psr18ClientDiscovery::find();
        }

        return $this->httpClient;
    }

    private function getRequestFactory(): RequestFactoryInterface
    {
        if ($this->requestFactory === null) {
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        }

        return $this->requestFactory;
    }

    private function getStreamFactory(): StreamFactoryInterface
    {
        if ($this->streamFactory === null) {
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
}
