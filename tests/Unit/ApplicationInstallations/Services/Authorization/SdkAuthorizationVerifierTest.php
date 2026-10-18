<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationInstallations\Services\Authorization;

use Bitrix24\Lib\ApplicationInstallations\Services\Authorization\SdkAuthorizationVerifier;
use Bitrix24\Lib\ApplicationInstallations\Services\Authorization\InvalidAuthorizationException;
use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SdkAuthorizationVerifierTest extends TestCase
{
    public function testSecureIdentityIsCheckedBeforeProfileAndExpiryIsAuthoritative(): void
    {
        $urls = [];
        $expires = time() + 1800;
        $mockHttpClient = new MockHttpClient(function (string $method, string $url) use (&$urls, $expires): MockResponse {
            $urls[] = $url;
            return new MockResponse(json_encode(['result' => count($urls) === 1
                ? $this->metadata($expires)
                : ['ID' => '7', 'ADMIN' => true]], JSON_THROW_ON_ERROR));
        });
        $result = $this->verifier($mockHttpClient)->verify(new AuthToken('access', 'refresh', time() + 3600), new Domain('example.bitrix24.ru'), 'member', 7);
        self::assertSame(7, $result->userId);
        self::assertSame($expires, $result->authToken->expires);
        self::assertStringStartsWith('https://oauth.bitrix24.tech/', $urls[0]);
        self::assertStringStartsWith('https://example.bitrix24.ru/', $urls[1]);
    }

    public function testForeignApplicationNeverReceivesPortalRequest(): void
    {
        $calls = 0;
        $mockHttpClient = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;
            $metadata = $this->metadata(time() + 1000);
            $metadata['client_id'] = 'foreign';
            return new MockResponse(json_encode(['result' => $metadata], JSON_THROW_ON_ERROR));
        });
        try {
            $this->verifier($mockHttpClient)->verify(new AuthToken('access-secret', 'refresh-secret', time() + 3600), new Domain('example.bitrix24.ru'), 'member');
            self::fail('Foreign application accepted');
        } catch (InvalidAuthorizationException $invalidAuthorizationException) {
            self::assertSame(1, $calls);
            self::assertStringNotContainsString('secret', $invalidAuthorizationException->getMessage());
            self::assertNull($invalidAuthorizationException->getPrevious());
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMetadata')]
    public function testInvalidMetadataStopsBeforePortal(array $changes): void
    {
        $calls = 0;
        $mockHttpClient = new MockHttpClient(function () use (&$calls, $changes): MockResponse {
            ++$calls;
            return new MockResponse(json_encode(['result' => array_replace_recursive($this->metadata(time() + 600), $changes)], JSON_THROW_ON_ERROR));
        });
        try {
            $this->verifier($mockHttpClient)->verify(new AuthToken('access', null, time() + 600), new Domain('example.bitrix24.ru'), 'member');
            self::fail('Invalid metadata accepted');
        } catch (InvalidAuthorizationException) {
            self::assertSame(1, $calls);
        }
    }

    public static function invalidMetadata(): iterable
    {
        yield 'foreign member' => [['install' => ['member_id' => 'other']]];
        yield 'foreign domain' => [['install' => ['uri' => 'https://other.bitrix24.ru/']]];
        yield 'insecure domain' => [['install' => ['uri' => 'http://example.bitrix24.ru/']]];
        yield 'userinfo' => [['install' => ['uri' => 'https://user@example.bitrix24.ru/']]];
        yield 'unexpected path' => [['install' => ['uri' => 'https://example.bitrix24.ru/rest/']]];
        yield 'missing installation' => [['install' => null]];
        yield 'invalid user' => [['user_id' => 0]];
        yield 'malformed user' => [['user_id' => '7malformed']];
        yield 'expired' => [['expires' => 1]];
        yield 'invalid expiry' => [['expires' => 'tomorrow']];
        yield 'boolean expiry' => [['expires' => true]];
        yield 'overflow date' => [['expires' => '2099-02-31T01:02:03Z']];
    }

    public function testProfileUserMustMatchSecureIdentity(): void
    {
        $mockHttpClient = new MockHttpClient([
            new MockResponse(json_encode(['result' => $this->metadata(time() + 600)], JSON_THROW_ON_ERROR)),
            new MockResponse('{"result":{"ID":"8","ADMIN":true}}'),
        ]);
        $this->expectException(InvalidAuthorizationException::class);
        $this->verifier($mockHttpClient)->verify(new AuthToken('access', null, time() + 600), new Domain('example.bitrix24.ru'), 'member');
    }

    public function testExpectedUserMismatchStopsBeforeProfile(): void
    {
        $mockHttpClient = new MockHttpClient([new MockResponse(json_encode(['result' => $this->metadata(time() + 600)], JSON_THROW_ON_ERROR))]);
        $this->expectException(InvalidAuthorizationException::class);
        $this->verifier($mockHttpClient)->verify(new AuthToken('access', null, time() + 600), new Domain('example.bitrix24.ru'), 'member', 8);
    }

    public function testUpstreamFailureDoesNotExposeSecrets(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse('secret upstream content', ['http_code' => 503]));
        try {
            $this->verifier($mockHttpClient)->verify(new AuthToken('access-secret', 'refresh-secret', time() + 600), new Domain('example.bitrix24.ru'), 'member');
            self::fail('Upstream failure accepted');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Authorization\AuthorizationUnavailableException $authorizationUnavailableException) {
            self::assertStringNotContainsString('secret', $authorizationUnavailableException->getMessage());
            self::assertNull($authorizationUnavailableException->getPrevious());
        }
    }

    public function testSequentialVerificationsUseTheirOwnCredentials(): void
    {
        $tokens = [];
        $mockHttpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$tokens): MockResponse {
            $body = json_decode((string) $options['body'], true, flags: JSON_THROW_ON_ERROR);
            $tokens[] = $body['auth'];
            return new MockResponse(json_encode(['result' => str_contains($url, 'app.info')
                ? $this->metadata(time() + 600) : ['ID' => '7', 'ADMIN' => 'Y']], JSON_THROW_ON_ERROR));
        });
        $verifier = $this->verifier($mockHttpClient);
        foreach (['first', 'second'] as $access) {
            $verifier->verify(new AuthToken($access, null, time() + 600), new Domain('example.bitrix24.ru'), 'member');
        }

        self::assertSame(['first', 'first', 'second', 'second'], $tokens);
    }

    public function testSdkRefreshReturnsVerifiedEffectiveCredentials(): void
    {
        $metadata = $this->metadata(time() + 600);
        $metadata['expires'] = time() + 600;
        $mockHttpClient = new MockHttpClient([
            new MockResponse('{"error":"expired_token"}', ['http_code' => 401]),
            new MockResponse(json_encode(['access_token' => 'renewed', 'refresh_token' => 'renewed-refresh',
                'expires' => time() + 3600, 'expires_in' => 3600, 'member_id' => 'member',
                'client_endpoint' => 'https://example.bitrix24.ru/rest/', 'server_endpoint' => 'https://oauth.bitrix24.tech/rest/',
                'status' => 'F', 'domain' => 'example.bitrix24.ru'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['result' => $metadata], JSON_THROW_ON_ERROR)),
            new MockResponse('{"result":{"ID":"7","ADMIN":true}}'),
            new MockResponse(json_encode(['result' => $metadata], JSON_THROW_ON_ERROR)),
            new MockResponse('{"result":{"ID":"7","ADMIN":true}}'),
        ]);
        $verified = $this->verifier($mockHttpClient)->verify(new AuthToken('expired', 'refresh', 1), new Domain('example.bitrix24.ru'), 'member');
        self::assertSame('renewed', $verified->authToken->accessToken);
        self::assertSame('renewed-refresh', $verified->authToken->refreshToken);
        self::assertSame($metadata['expires'], $verified->authToken->expires);
    }

    public function testPortalRedirectNeverReceivesCredentials(): void
    {
        $urls = [];
        $mockHttpClient = new MockHttpClient(function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;
            if (count($urls) > 2) {
                throw new \RuntimeException('Unexpected redirect request');
            }

            if (count($urls) === 1) {
                return new MockResponse(json_encode(['result' => $this->metadata(time() + 600)], JSON_THROW_ON_ERROR));
            }

            return new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://untrusted.example/']]);
        });
        try {
            $this->verifier($mockHttpClient)->verify(new AuthToken('access-secret', null, time() + 600), new Domain('example.bitrix24.ru'), 'member');
            self::fail('Redirect accepted');
        } catch (\Bitrix24\Lib\ApplicationInstallations\Services\Authorization\AuthorizationUnavailableException) {
            self::assertCount(2, $urls);
            self::assertStringStartsWith('https://example.bitrix24.ru/', $urls[1]);
        }
    }

    public function testInvalidTokenIsRejectionRatherThanOutage(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse('{"error":"invalid_token","error_description":"secret"}', ['http_code' => 401]));
        $this->expectException(InvalidAuthorizationException::class);
        $this->verifier($mockHttpClient)->verify(new AuthToken('access', null, time() + 600), new Domain('example.bitrix24.ru'), 'member');
    }

    private function verifier(MockHttpClient $http): SdkAuthorizationVerifier
    {
        return new SdkAuthorizationVerifier(new ApplicationProfile('client', 'client-secret', new Scope(['crm'])), 'https://oauth.bitrix24.tech/', $http);
    }

    private function metadata(int $expires): array
    {
        return ['client_id' => 'client', 'user_id' => 7, 'expires' => date(DATE_ATOM, $expires),
            'install' => ['member_id' => 'member', 'uri' => 'https://example.bitrix24.ru/', 'installed' => false]];
    }
}
