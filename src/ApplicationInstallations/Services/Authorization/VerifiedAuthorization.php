<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Services\Authorization;

use Bitrix24\Lib\Common\ValueObjects\Domain;
use Bitrix24\SDK\Core\Credentials\AuthToken;

final readonly class VerifiedAuthorization
{
    /** @param array<string, mixed> $profile */
    public function __construct(
        public string $clientId,
        public string $memberId,
        public Domain $domain,
        public int $userId,
        public AuthToken $authToken,
        public array $profile,
    ) {}

    public function isAdmin(): bool
    {
        return true === ($this->profile['ADMIN'] ?? null)
            || 'Y' === ($this->profile['ADMIN'] ?? null)
            || 1 === ($this->profile['ADMIN'] ?? null)
            || '1' === ($this->profile['ADMIN'] ?? null);
    }
}
