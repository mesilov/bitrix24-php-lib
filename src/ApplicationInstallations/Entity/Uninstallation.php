<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationInstallations\Entity;

use Bitrix24\Lib\ApplicationInstallations\Services\Uninstall\UninstallationContext;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/** Durable retry receipt, not an installation or an authorization credential. */
class Uninstallation
{
    private readonly string $tokenHash;

    private readonly string $memberHash;

    private readonly CarbonImmutable $requestedAt;

    private ?CarbonImmutable $completedAt = null;

    public function __construct(
        private readonly Uuid $installationId,
        #[\SensitiveParameter]
        string $applicationToken,
        string $memberId,
        private readonly bool $clean,
    ) {
        $this->tokenHash = hash('sha256', $applicationToken);
        $this->memberHash = hash('sha256', $memberId);
        $this->requestedAt = new CarbonImmutable();
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getRequestedAt(): CarbonImmutable
    {
        return $this->requestedAt;
    }

    public function getInstallationId(): Uuid
    {
        return $this->installationId;
    }

    public function getCompletedAt(): ?CarbonImmutable
    {
        return $this->completedAt;
    }

    public function contextFor(string $memberId): ?UninstallationContext
    {
        if (!hash_equals($this->memberHash, hash('sha256', $memberId))) {
            return null;
        }

        return new UninstallationContext($this->installationId, $memberId, $this->clean);
    }

    public function complete(): void
    {
        $this->completedAt ??= new CarbonImmutable();
    }
}
