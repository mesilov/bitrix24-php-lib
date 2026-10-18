<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Authorization;

use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\SDK\Core\Credentials\AuthToken;

interface AuthorizationVerifierInterface
{
    /**
     * @throws InvalidAuthorizationException
     * @throws AuthorizationUnavailableException
     */
    public function verify(AuthToken $authToken, Domain $domain, string $memberId, ?int $expectedUserId = null): VerifiedAuthorization;
}
