<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Override;
use Symfony\Component\Uid\Uuid;

class Bitrix24AccountRepository extends EntityRepository implements Bitrix24AccountRepositoryInterface
{
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($entityManager, $entityManager->getClassMetadata(Bitrix24Account::class));
    }

    /**
     * @phpstan-return Bitrix24AccountInterface&AggregateRootEventsEmitterInterface
     *
     * @throws Bitrix24AccountNotFoundException
     */
    #[Override]
    public function getById(Uuid $uuid): Bitrix24AccountInterface
    {
        $account = $this->getEntityManager()->getRepository(Bitrix24Account::class)->find($uuid);
        if (null === $account || $account->getStatus() === Bitrix24AccountStatus::deleted) {
            throw new Bitrix24AccountNotFoundException(
                sprintf('bitrix24 account not found by id %s', $uuid->toRfc4122())
            );
        }


        return $account;
    }

    #[Override]
    public function save(Bitrix24AccountInterface $bitrix24Account): void
    {
        $this->getEntityManager()->persist($bitrix24Account);
    }

    /**
     * @phpstan-return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function findByMemberId(
        string $memberId,
        ?Bitrix24AccountStatus $bitrix24AccountStatus = null,
        ?int $bitrix24UserId = null,
        ?bool $isAdmin = null
    ): array {
        if ('' === trim($memberId)) {
            throw new InvalidArgumentException('memberId cannot be empty');
        }

        $criteria = [
            'memberId' => $memberId,
        ];
        if ($bitrix24AccountStatus instanceof Bitrix24AccountStatus) {
            $criteria['status'] = $bitrix24AccountStatus->name;
        }
        if (null !== $bitrix24UserId) {
            $criteria['bitrix24UserId'] = $bitrix24UserId;
        }

        if (null !== $isAdmin) {
            $criteria['isBitrix24UserAdmin'] = $isAdmin;
        }

        return $this->findBy($criteria);
    }

    #[Override]
    public function delete(Uuid $uuid): void
    {
        $bitrix24Account = $this->getEntityManager()->getRepository(Bitrix24Account::class)->find($uuid);

        if (null === $bitrix24Account) {
            throw new Bitrix24AccountNotFoundException(
                sprintf('bitrix24 account not found by id %s', $uuid->toRfc4122())
            );
        }
        if (Bitrix24AccountStatus::deleted !== $bitrix24Account->getStatus()) {
            throw new InvalidArgumentException(
                sprintf(
                    'you cannot delete bitrix24account «%s», they must be in status «deleted», current status «%s»',
                    $bitrix24Account->getId()->toRfc4122(),
                    $bitrix24Account->getStatus()->name
                )
            );
        }
        $bitrix24Account->setStatus(Bitrix24AccountStatus::deleted);
        $this->save($bitrix24Account);
    }

    public function findAllActive(int|null $limit = null, int|null $offset = null): array
    {
        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findBy(
            [
                'status' => Bitrix24AccountStatus::active,
            ],
            null,
            $limit,
            $offset
        );
    }

    /**
     * @param non-empty-string $applicationToken
     *
     * @phpstan-return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     * @throws InvalidArgumentException
     */
    public function findByApplicationToken(string $applicationToken): array
    {
        if ($applicationToken === '') {
            throw new InvalidArgumentException('application token cannot be an empty string');
        }

        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findBy(
            [
                'applicationToken' => $applicationToken,
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     *
     * @phpstan-return Bitrix24AccountInterface&AggregateRootEventsEmitterInterface
     */
    #[Override]
    public function findOneAdminByMemberId(string $memberId): ?Bitrix24AccountInterface
    {
        if ('' === trim($memberId)) {
            throw new InvalidArgumentException('memberId cannot be an empty string');
        }

        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findOneBy(
            [
                'memberId' => $memberId,
                'isBitrix24UserAdmin' => true,
                'status' => [Bitrix24AccountStatus::active, Bitrix24AccountStatus::new],
            ]
        );
    }

    /**
     * @phpstan-return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     *
     * @return array<AggregateRootEventsEmitterInterface&Bitrix24AccountInterface>
     *
     * @throws InvalidArgumentException
     */
    #[Override]
    public function findByDomain(
        string $domainUrl,
        ?Bitrix24AccountStatus $bitrix24AccountStatus = null,
        ?bool $isAdmin = null
    ): array {
        if ('' === trim($domainUrl)) {
            throw new InvalidArgumentException('domainUrl cannot be an empty string');
        }

        $criteria = [
            'domainUrl' => $domainUrl,
        ];
        if ($bitrix24AccountStatus instanceof Bitrix24AccountStatus) {
            $criteria['status'] = $bitrix24AccountStatus->name;
        }

        if (null !== $isAdmin) {
            $criteria['isBitrix24UserAdmin'] = $isAdmin;
        }

        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findBy($criteria);
    }
}
