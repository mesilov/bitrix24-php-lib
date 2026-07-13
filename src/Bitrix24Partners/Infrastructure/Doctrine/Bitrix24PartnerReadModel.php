<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Partners\Entity\Bitrix24Partner;
use Bitrix24\SDK\Application\Contracts\Bitrix24Partners\Entity\Bitrix24PartnerStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Read model for Bitrix24Partner projections.
 *
 * Returns plain scalar rows (Doctrine getArrayResult) — it does NOT hydrate
 * aggregate entities and stays free of any sync-scenario knowledge. Each use
 * case shapes its own DTO (e.g. Import's PartnerSyncView) from these rows.
 */
class Bitrix24PartnerReadModel
{
    /** @var EntityRepository<Bitrix24Partner> */
    private readonly EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $this->entityManager->getRepository(Bitrix24Partner::class);
    }

    /**
     * Returns all active partners as plain scalar rows (no hydration).
     *
     * The partners registry is a bounded, curated set (certified resellers,
     * not transactional data). As of 2026-06-24 there are ~3 000 partners in
     * the RU zone, so materializing the full active set is an intentionally
     * light load and no LIMIT/ceiling is applied. If the dataset ever grows
     * substantially, reconsider a paginated/streaming query.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllActiveAsArray(): array
    {
        return $this->repository
            ->createQueryBuilder('p')
            ->where('p.status != :status')
            ->setParameter('status', Bitrix24PartnerStatus::deleted)
            ->getQuery()
            ->getArrayResult()
        ;
    }
}
