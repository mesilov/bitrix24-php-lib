<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Authorization;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/** @internal Prevent SDK-level redirect handling from forwarding OAuth credentials. */
final readonly class RedirectRejectingHttpClient implements HttpClientInterface
{
    public function __construct(private HttpClientInterface $client) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $options['max_redirects'] = 0;
        $response = $this->client->request($method, $url, $options);
        $status = $response->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $response->cancel();

            throw new TransportException('Redirects are forbidden during OAuth verification.');
        }

        return $response;
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        $options['max_redirects'] = 0;

        return new self($this->client->withOptions($options));
    }
}
