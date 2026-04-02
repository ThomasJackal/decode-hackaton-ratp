<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{severity: string|null, cnt: int|string}>
     */
    public function countBySeverity(?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.severity AS severity', 'COUNT(r.id) AS cnt')
            ->groupBy('r.severity')
            ->orderBy('cnt', 'DESC');

        if (null !== $since) {
            $qb->andWhere('r.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @return list<array{situation_type: string|null, cnt: int|string}>
     */
    public function countTopSituationTypes(?\DateTimeImmutable $since = null, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.situationType AS situation_type', 'COUNT(r.id) AS cnt')
            ->groupBy('r.situationType')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $since) {
            $qb->andWhere('r.createdAt >= :since')->setParameter('since', $since);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @return list<Report>
     */
    public function findRecentForManagement(int $limit, int $offset = 0): array
    {
        return $this->managementListQueryBuilder()
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countManagementFiltered(
        ?\DateTimeImmutable $since = null,
        ?string $severity = null,
        ?string $situationType = null,
        ?int $driverId = null,
        ?int $busId = null,
    ): int {
        $qb = $this->createQueryBuilder('r')->select('COUNT(r.id)');
        $this->applyManagementFilters($qb, $since, $severity, $situationType, $driverId, $busId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Report>
     */
    public function findManagementFiltered(
        ?\DateTimeImmutable $since = null,
        ?string $severity = null,
        ?string $situationType = null,
        ?int $driverId = null,
        ?int $busId = null,
        int $limit = 25,
        int $offset = 0,
    ): array {
        $qb = $this->managementListQueryBuilder();
        $this->applyManagementFilters($qb, $since, $severity, $situationType, $driverId, $busId);
        $qb->setFirstResult($offset)->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    private function managementListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.driver', 'd')->addSelect('d')
            ->leftJoin('r.Bus', 'b')->addSelect('b')
            ->orderBy('r.createdAt', 'DESC');
    }

    /**
     * @return list<string>
     */
    public function findDistinctSeverityValues(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.severity AS v')
            ->distinct()
            ->orderBy('v', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): string => (string) $row['v'], $rows));
    }

    /**
     * @return list<string>
     */
    public function findDistinctSituationTypeValues(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.situationType AS v')
            ->distinct()
            ->orderBy('v', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): string => (string) $row['v'], $rows));
    }

    private function applyManagementFilters(
        QueryBuilder $qb,
        ?\DateTimeImmutable $since,
        ?string $severity,
        ?string $situationType,
        ?int $driverId,
        ?int $busId,
    ): void {
        if (null !== $since) {
            $qb->andWhere('r.createdAt >= :since')->setParameter('since', $since);
        }
        if (null !== $severity && '' !== $severity) {
            $qb->andWhere('r.severity = :severity')->setParameter('severity', $severity);
        }
        if (null !== $situationType && '' !== $situationType) {
            $qb->andWhere('r.situationType = :situationType')->setParameter('situationType', $situationType);
        }
        if (null !== $driverId) {
            $qb->andWhere('d.id = :driverId')->setParameter('driverId', $driverId);
        }
        if (null !== $busId) {
            $qb->andWhere('b.id = :busId')->setParameter('busId', $busId);
        }
    }
}
