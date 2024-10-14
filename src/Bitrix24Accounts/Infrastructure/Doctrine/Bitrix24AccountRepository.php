<?php

declare(strict_types=1);

namespace Bitrix24\SDK\Lib\Bitrix24Accounts\Infrastructure\Doctrine;

use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Exceptions\Bitrix24AccountNotFoundException;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Override;
use Symfony\Component\Uid\Uuid;

class Bitrix24AccountRepository extends EntityRepository implements Bitrix24AccountRepositoryInterface
{
    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(Bitrix24Account::class));
    }

    /**
     * @phpstan-return Bitrix24AccountInterface&AggregateRootEventsEmitterInterface
     * @throws Bitrix24AccountNotFoundException
     */
    #[Override]
    public function getById(Uuid $uuid): Bitrix24AccountInterface
    {
        $res = $this->getEntityManager()->getRepository(Bitrix24Account::class)->find($uuid);
        if ($res === null) {
            throw new Bitrix24AccountNotFoundException(sprintf('bitrix24 account not found by id %s', $uuid->toRfc4122()));
        }

        return $res;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function save(Bitrix24AccountInterface $bitrix24Account): void
    {
        $this->getEntityManager()->persist($bitrix24Account);
        //todo discuss add flush arg to contract or add flusher in usecases?
        $this->getEntityManager()->flush();
    }

    /**
     * @phpstan-return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     */
    #[Override]
    public function findByMemberId(string $memberId, ?Bitrix24AccountStatus $bitrix24AccountStatus = null, ?bool $isAdmin = null): array
    {
        $criteria = [
            'memberId' => $memberId
        ];
        if ($bitrix24AccountStatus instanceof Bitrix24AccountStatus) {
            $criteria['status'] = $bitrix24AccountStatus->name;
        }

        if ($isAdmin !== null) {
            $criteria['isBitrix24UserAdmin'] = $isAdmin;
        }

        return $this->findBy($criteria);
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function delete(Uuid $uuid, bool $flush = false): void
    {
        $bitrix24Account = $this->getEntityManager()->getRepository(Bitrix24Account::class)->find($uuid);
        if ($bitrix24Account === null) {
            throw new Bitrix24AccountNotFoundException(sprintf('bitrix24 account not found by id %s', $uuid->toRfc4122()));
        }

        $this->getEntityManager()->remove($bitrix24Account);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllActive(): array
    {
        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findBy(
            [
                'status' => Bitrix24AccountStatus::active,
            ]
        );
    }

    /**
     * @param non-empty-string $applicationToken
     * @phpstan-return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     * @todo discuss are we need add this method in contract in b24phpsdk?
     */
    public function findByApplicationToken(string $applicationToken): array
    {
        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findBy(
            [
                'applicationToken' => $applicationToken,
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     * @phpstan-return Bitrix24AccountInterface&AggregateRootEventsEmitterInterface
     */
    #[Override]
    public function findOneAdminByMemberId(string $memberId): ?Bitrix24AccountInterface
    {
        if (trim($memberId) === '') {
            throw new InvalidArgumentException('memberId cannot be an empty string');
        }

        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findOneBy(
            [
                'memberId' => $memberId,
                'isBitrix24UserAdmin' => true,
                'status' => Bitrix24AccountStatus::active,
            ]
        );
    }

    /**
     * @phpstan-return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     * @return array<Bitrix24AccountInterface&AggregateRootEventsEmitterInterface>
     * @throws InvalidArgumentException
     */
    #[Override]
    public function findByDomain(string $domainUrl, ?Bitrix24AccountStatus $bitrix24AccountStatus = null, ?bool $isAdmin = null): array
    {
        if (trim($domainUrl) === '') {
            throw new InvalidArgumentException('domainUrl cannot be an empty string');
        }

        $criteria = [
            'domainUrl' => $domainUrl,
        ];
        if ($bitrix24AccountStatus instanceof Bitrix24AccountStatus) {
            $criteria['status'] = $bitrix24AccountStatus->name;
        }

        if ($isAdmin !== null) {
            $criteria['isBitrix24UserAdmin'] = $isAdmin;
        }

        return $this->getEntityManager()->getRepository(Bitrix24Account::class)->findBy($criteria);
    }
}