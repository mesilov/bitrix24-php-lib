<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Authorization;

use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\SDK\Core\CoreBuilder;
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Credentials;
use Bitrix24\SDK\Core\Credentials\Endpoints;
use Bitrix24\SDK\Core\Exceptions\AuthForbiddenException;
use Bitrix24\SDK\Core\Exceptions\BaseException;
use Bitrix24\SDK\Core\Exceptions\InvalidGrantException;
use Bitrix24\SDK\Core\Exceptions\WrongClientException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SdkAuthorizationVerifier implements AuthorizationVerifierInterface
{
    private HttpClientInterface $httpClient;

    /** The application profile and OAuth server URL must come from trusted server configuration. */
    public function __construct(
        private ApplicationProfile $applicationProfile,
        private string $oauthServerUrl,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = new RedirectRejectingHttpClient($httpClient ?? HttpClient::create(['timeout' => 15, 'max_redirects' => 0]));
    }

    public function verify(AuthToken $authToken, Domain $domain, string $memberId, ?int $expectedUserId = null): VerifiedAuthorization
    {
        return $this->verifyToken($authToken, $domain, $memberId, $expectedUserId, true);
    }

    private function verifyToken(AuthToken $authToken, Domain $domain, string $memberId, ?int $expectedUserId, bool $allowRefresh): VerifiedAuthorization
    {
        // Never reuse a builder: it caches the API client and its mutable credentials.
        $credentials = Credentials::createFromOAuth(
            $authToken,
            $this->applicationProfile,
            new Endpoints('https://'.$domain->value, $this->oauthServerUrl)
        );
        $core = (new CoreBuilder())->withCredentials($credentials)->withHttpClient($this->httpClient)->build();

        try {
            // The SDK routes this request to the configured OAuth server, not to the supplied portal.
            $metadata = $core->call('app.info', ['IS_NEED_OAUTH_SECURE_CHECK' => true])->getResponseData()->getResult();
            $install = $metadata['install'] ?? null;
            $userId = $this->positiveInteger($metadata['user_id'] ?? null);
            $expires = $this->expiry($metadata['expires'] ?? null);
            if (($metadata['client_id'] ?? null) !== $this->applicationProfile->clientId
                || !is_array($install)
                || ($install['member_id'] ?? null) !== $memberId
                || !$this->matchesDomain($install['uri'] ?? null, $domain)
                || null === $userId
                || (null !== $expectedUserId && $expectedUserId !== $userId)
                || null === $expires || $expires <= time()) {
                throw new InvalidAuthorizationException();
            }

            // Do not require installed=true: the installation may still be pending.
            $profile = $core->call('profile')->getResponseData()->getResult();
            if ($this->positiveInteger($profile['ID'] ?? null) !== $userId
                || !array_key_exists('ADMIN', $profile)
                || !in_array($profile['ADMIN'], [true, false, 'Y', 'N', 1, 0, '1', '0'], true)) {
                throw new InvalidAuthorizationException();
            }

            $effective = $credentials->getAuthToken();
            if (!$effective instanceof AuthToken) {
                throw new InvalidAuthorizationException();
            }

            // A refresh while reading profile needs a new secure check for the effective token.
            if ($effective->accessToken !== $authToken->accessToken) {
                if (!$allowRefresh) {
                    throw new InvalidAuthorizationException();
                }

                return $this->verifyToken($effective, $domain, $memberId, $userId, false);
            }

            return new VerifiedAuthorization(
                $this->applicationProfile->clientId,
                $memberId,
                $domain,
                $userId,
                new AuthToken($effective->accessToken, $effective->refreshToken, $expires, max(0, $expires - time())),
                $profile
            );
        } catch (InvalidAuthorizationException $exception) {
            throw $exception;
        } catch (AuthForbiddenException|InvalidGrantException|WrongClientException) {
            throw new InvalidAuthorizationException();
        } catch (BaseException $exception) {
            // Older SDK versions expose some OAuth error codes only as a message prefix.
            if (preg_match('/^(?:invalid_token|expired_token|no_auth_found|invalid_grant)(?: -|$)/i', $exception->getMessage())
                || 'UNAUTHORIZED request error' === $exception->getMessage()) {
                throw new InvalidAuthorizationException();
            }

            throw new AuthorizationUnavailableException();
        } catch (\Throwable) {
            // SDK exception chains and response bodies may contain credentials. Do not expose them.
            throw new AuthorizationUnavailableException();
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return false === $parsed ? null : $parsed;
    }

    private function expiry(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return $this->positiveInteger($value);
        }

        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return null;
        }

        try {
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();

            return false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0) ? null : $date->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }

    private function matchesDomain(mixed $uri, Domain $domain): bool
    {
        if (!is_string($uri)) {
            return false;
        }

        $parts = parse_url($uri);

        return is_array($parts) && 'https' === ($parts['scheme'] ?? null)
            && strtolower($domain->value) === strtolower($parts['host'] ?? '')
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['port']) && !isset($parts['query']) && !isset($parts['fragment'])
            && in_array($parts['path'] ?? '', ['', '/'], true);
    }
}
