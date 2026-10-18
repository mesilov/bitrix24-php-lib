<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers\ApplicationInstallations;

use Bitrix24\Lib\ApplicationInstallations\Services\Authorization\AuthorizationVerifierInterface;
use Bitrix24\Lib\ApplicationInstallations\Services\Authorization\InvalidAuthorizationException;
use Bitrix24\Lib\ApplicationInstallations\Services\Authorization\VerifiedAuthorization;
use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\SDK\Core\Credentials\AuthToken;

final class RecordingAuthorizationVerifier implements AuthorizationVerifierInterface
{
    public bool $reject = false;

    public int $userId = 1;

    public bool $admin = true;

    public ?AuthToken $effectiveToken = null;

    public array $calls = [];

    public function verify(AuthToken $authToken, Domain $domain, string $memberId, ?int $expectedUserId = null): VerifiedAuthorization
    {
        $this->calls[] = [$authToken, $domain, $memberId, $expectedUserId];
        if ($this->reject) {
            throw new InvalidAuthorizationException();
        }

        return new VerifiedAuthorization('test-client', $memberId, $domain, $this->userId,
            $this->effectiveToken ?? $authToken, ['ID' => $this->userId, 'ADMIN' => $this->admin]);
    }
}
