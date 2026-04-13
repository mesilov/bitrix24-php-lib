<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Helpers\ApplicationInstallations;

use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Exceptions\ApplicationInstallationNotFoundException;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Temporary compatibility in-memory repository for use-case tests.
 *
 * @deprecated Remove after upstream fix for bitrix24/b24phpsdk#387:
 *             https://github.com/bitrix24/b24phpsdk/issues/387
 *             The SDK in-memory repository currently does not resolve pending
 *             installations linked to a master account in `new` status.
 */
class RecordingApplicationInstallationInMemoryRepository implements ApplicationInstallationRepositoryInterface
{
    /** @var array<string, ApplicationInstallationInterface> */
    private array $items = [];

    private int $saveCalls = 0;

    public function __construct(
        private readonly Bitrix24AccountRepositoryInterface $bitrix24AccountRepository
    ) {}

    #[\Override]
    public function save(ApplicationInstallationInterface $applicationInstallation): void
    {
        $this->saveCalls++;
        $this->items[$applicationInstallation->getId()->toRfc4122()] = $applicationInstallation;
    }

    #[\Override]
    public function delete(Uuid $uuid): void
    {
        $applicationInstallation = $this->getById($uuid);
        if (ApplicationInstallationStatus::deleted !== $applicationInstallation->getStatus()) {
            throw new InvalidArgumentException(
                sprintf(
                    'you cannot delete application installation «%s», in status «%s», mark applicatoin installation as «deleted» before',
                    $applicationInstallation->getId()->toRfc4122(),
                    $applicationInstallation->getStatus()->name,
                )
            );
        }

        unset($this->items[$uuid->toRfc4122()]);
    }

    #[\Override]
    public function getById(Uuid $uuid): ApplicationInstallationInterface
    {
        if (!array_key_exists($uuid->toRfc4122(), $this->items)) {
            throw new ApplicationInstallationNotFoundException(
                sprintf('application installation not found by id «%s» ', $uuid->toRfc4122())
            );
        }

        return $this->items[$uuid->toRfc4122()];
    }

    #[\Override]
    public function findByBitrix24AccountId(Uuid $uuid): ?ApplicationInstallationInterface
    {
        foreach ($this->items as $item) {
            if ($item->getBitrix24AccountId()->equals($uuid)) {
                return $item;
            }
        }

        return null;
    }

    #[\Override]
    public function findByExternalId(string $externalId): array
    {
        if (trim($externalId) === '') {
            throw new InvalidArgumentException('external id cannot be empty string');
        }

        $result = [];
        foreach ($this->items as $item) {
            if ($item->getExternalId() === $externalId) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Find the current installation for a member id across non-deleted master accounts.
     * This supports both pending (`new`) and finished (`active`) install flows.
     */
    #[\Override]
    public function findByBitrix24AccountMemberId(string $memberId): ?ApplicationInstallationInterface
    {
        if (trim($memberId) === '') {
            throw new InvalidArgumentException('memberId id cannot be empty string');
        }

        $bitrix24Accounts = $this->bitrix24AccountRepository->findByMemberId($memberId);

        $masterAccounts = array_values(array_filter(
            $bitrix24Accounts,
            static fn (Bitrix24AccountInterface $bitrix24Account): bool => $bitrix24Account->isMasterAccount()
                && Bitrix24AccountStatus::deleted !== $bitrix24Account->getStatus()
        ));

        usort(
            $masterAccounts,
            static fn (Bitrix24AccountInterface $left, Bitrix24AccountInterface $right): int
                => self::getStatusPriority($left->getStatus()) <=> self::getStatusPriority($right->getStatus())
        );

        foreach ($masterAccounts as $masterAccount) {
            foreach ($this->items as $item) {
                if ($item->getBitrix24AccountId()->equals($masterAccount->getId())
                    && ApplicationInstallationStatus::deleted !== $item->getStatus()
                ) {
                    return $item;
                }
            }
        }

        return null;
    }

    #[\Override]
    public function findByApplicationToken(string $applicationToken): ?ApplicationInstallationInterface
    {
        if (trim($applicationToken) === '') {
            throw new InvalidArgumentException('applicationToken id cannot be empty string');
        }

        foreach ($this->items as $item) {
            if ($item->isApplicationTokenValid($applicationToken)) {
                return $item;
            }
        }

        return null;
    }

    public function getSaveCalls(): int
    {
        return $this->saveCalls;
    }

    private static function getStatusPriority(Bitrix24AccountStatus $bitrix24AccountStatus): int
    {
        return match ($bitrix24AccountStatus) {
            Bitrix24AccountStatus::active => 0,
            Bitrix24AccountStatus::new => 1,
            default => 2,
        };
    }
}
