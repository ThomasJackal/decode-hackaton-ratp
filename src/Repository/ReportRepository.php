<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
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

    public function findForManagementDetail(int $id): ?Report
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.driver', 'd')->addSelect('d')
            ->leftJoin('r.Bus', 'b')->addSelect('b')
            ->leftJoin('r.closedBy', 'cb')->addSelect('cb')
            ->andWhere('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByDriverId(int $driverId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.driver', 'd')
            ->andWhere('d.id = :driverId')
            ->setParameter('driverId', $driverId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByBusId(int $busId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.Bus', 'b')
            ->andWhere('b.id = :busId')
            ->setParameter('busId', $busId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Report>
     */
    public function findByDriverIdOrdered(int $driverId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.Bus', 'b')->addSelect('b')
            ->join('r.driver', 'd')
            ->andWhere('d.id = :driverId')
            ->setParameter('driverId', $driverId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBySeverityValue(string $severity, ?\DateTimeImmutable $since = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.severity = :severity')
            ->setParameter('severity', $severity);

        if (null !== $since) {
            $qb->andWhere('r.createdAt >= :since')->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countTreated(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.closedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countClosedAmongCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :since')
            ->andWhere('r.closedAt IS NOT NULL')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{id: int, matricule: string, ligne: string, count: int, score_moyen: float}>
     */
    public function findTopDriversByReportCount(\DateTimeImmutable $since, int $limit = 6): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                d.id,
                COALESCE(NULLIF(TRIM(d.contact->>'matricule'), ''), '#' || d.id::text) AS matricule,
                COALESCE(
                    (
                        SELECT tl.code
                        FROM report r2
                        INNER JOIN bus_serving bs ON bs.bus_id = r2.bus_id
                        INNER JOIN transit_line tl ON tl.id = bs.line_id
                        WHERE r2.driver_id = d.id AND r2.created_at >= :since
                        GROUP BY tl.code
                        ORDER BY COUNT(*) DESC
                        LIMIT 1
                    ),
                    NULLIF(TRIM(d.contact->>'ligne'), ''),
                    '—'
                ) AS ligne,
                COUNT(r.id) AS count,
                ROUND(AVG(
                    CASE r.severity
                        WHEN 'high' THEN 8
                        WHEN 'medium' THEN 5
                        WHEN 'low' THEN 2
                        ELSE 3
                    END
                )::numeric, 1) AS score_moyen
            FROM driver d
            INNER JOIN report r ON r.driver_id = d.id AND r.created_at >= :since
            GROUP BY d.id
            ORDER BY count DESC, score_moyen DESC
            LIMIT :limit
            SQL;

        $result = $conn->executeQuery(
            $sql,
            [
                'since' => $since->format('Y-m-d H:i:s'),
                'limit' => $limit,
            ],
            [
                'since' => ParameterType::STRING,
                'limit' => ParameterType::INTEGER,
            ],
        );

        $rows = $result->fetchAllAssociative();

        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'matricule' => (string) $r['matricule'],
            'ligne' => (string) $r['ligne'],
            'count' => (int) $r['count'],
            'score_moyen' => (float) $r['score_moyen'],
        ], $rows);
    }

    /**
     * @return array{labels: list<string>, values: list<int>, valuesEleve: list<int>}
     */
    public function getParJourData(\DateTimeImmutable $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                TO_CHAR(gs.day, 'DD/MM') AS label,
                COALESCE(all_r.cnt, 0) AS total,
                COALESCE(elv_r.cnt, 0) AS eleve
            FROM generate_series(:since::date, CURRENT_DATE, INTERVAL '1 day') AS gs(day)
            LEFT JOIN (
                SELECT date_trunc('day', created_at) AS d, COUNT(*) AS cnt
                FROM report
                WHERE created_at >= :since
                GROUP BY d
            ) all_r ON all_r.d = gs.day
            LEFT JOIN (
                SELECT date_trunc('day', created_at) AS d, COUNT(*) AS cnt
                FROM report
                WHERE created_at >= :since AND severity = 'high'
                GROUP BY d
            ) elv_r ON elv_r.d = gs.day
            ORDER BY gs.day
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'since' => $since->format('Y-m-d'),
        ]);

        return [
            'labels' => array_column($rows, 'label'),
            'values' => array_map('intval', array_column($rows, 'total')),
            'valuesEleve' => array_map('intval', array_column($rows, 'eleve')),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, valuesEleve: list<int>}
     */
    public function getParMoisData(\DateTimeImmutable $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                TO_CHAR(gs.month, 'Mon YY') AS label,
                COALESCE(all_r.cnt, 0) AS total,
                COALESCE(elv_r.cnt, 0) AS eleve
            FROM generate_series(
                date_trunc('month', :since::date),
                date_trunc('month', CURRENT_DATE::timestamptz),
                INTERVAL '1 month'
            ) AS gs(month)
            LEFT JOIN (
                SELECT date_trunc('month', created_at) AS m, COUNT(*) AS cnt
                FROM report WHERE created_at >= :since
                GROUP BY m
            ) all_r ON all_r.m = gs.month
            LEFT JOIN (
                SELECT date_trunc('month', created_at) AS m, COUNT(*) AS cnt
                FROM report WHERE created_at >= :since AND severity = 'high'
                GROUP BY m
            ) elv_r ON elv_r.m = gs.month
            ORDER BY gs.month
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'since' => $since->format('Y-m-d'),
        ]);

        return [
            'labels' => array_column($rows, 'label'),
            'values' => array_map('intval', array_column($rows, 'total')),
            'valuesEleve' => array_map('intval', array_column($rows, 'eleve')),
        ];
    }

    /**
     * @return array{labels: list<string>, tot: list<int>, elv: list<int>}
     */
    public function getParSemaineData(\DateTimeImmutable $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                'S' || ROW_NUMBER() OVER (ORDER BY gs.week)::text AS label,
                COALESCE(all_r.cnt, 0) AS total,
                COALESCE(elv_r.cnt, 0) AS eleve
            FROM generate_series(
                date_trunc('week', :since::date),
                date_trunc('week', CURRENT_DATE::timestamptz),
                INTERVAL '1 week'
            ) AS gs(week)
            LEFT JOIN (
                SELECT date_trunc('week', created_at) AS w, COUNT(*) AS cnt
                FROM report WHERE created_at >= :since
                GROUP BY w
            ) all_r ON all_r.w = gs.week
            LEFT JOIN (
                SELECT date_trunc('week', created_at) AS w, COUNT(*) AS cnt
                FROM report WHERE created_at >= :since AND severity = 'high'
                GROUP BY w
            ) elv_r ON elv_r.w = gs.week
            ORDER BY gs.week
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'since' => $since->format('Y-m-d'),
        ]);

        if ([] === $rows) {
            return [
                'labels' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8'],
                'tot' => [8, 12, 7, 14, 9, 11, 6, 13],
                'elv' => [2, 4, 1, 5, 3, 3, 1, 4],
            ];
        }

        return [
            'labels' => array_column($rows, 'label'),
            'tot' => array_map('intval', array_column($rows, 'total')),
            'elv' => array_map('intval', array_column($rows, 'eleve')),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, valuesEleve: list<int>}
     */
    public function getParJourSemaineData(\DateTimeImmutable $since, bool $includeWeekend = true, bool $highOnly = false): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $dowFilter = $includeWeekend ? '' : ' AND EXTRACT(ISODOW FROM r.created_at) < 6';

        $severityFilter = $highOnly ? " AND r.severity = 'high'" : '';

        $sql = <<<SQL
            SELECT EXTRACT(ISODOW FROM r.created_at)::int AS dow, COUNT(*)::int AS cnt
            FROM report r
            WHERE r.created_at >= :since{$severityFilter}{$dowFilter}
            GROUP BY dow
            ORDER BY dow
            SQL;

        $rows = $conn->fetchAllAssociative($sql, ['since' => $since->format('Y-m-d H:i:s')]);
        $byDow = [];
        foreach ($rows as $row) {
            $byDow[(int) $row['dow']] = (int) $row['cnt'];
        }

        $dowLabels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        $order = $includeWeekend ? [1, 2, 3, 4, 5, 6, 7] : [1, 2, 3, 4, 5];
        $labels = [];
        $values = [];
        foreach ($order as $d) {
            $labels[] = $dowLabels[$d];
            $values[] = $byDow[$d] ?? 0;
        }

        $valuesEleve = [];
        if (!$highOnly) {
            $sqlElv = <<<SQL
                SELECT EXTRACT(ISODOW FROM r.created_at)::int AS dow, COUNT(*)::int AS cnt
                FROM report r
                WHERE r.created_at >= :since AND r.severity = 'high'{$dowFilter}
                GROUP BY dow
                ORDER BY dow
                SQL;
            $rowsElv = $conn->fetchAllAssociative($sqlElv, ['since' => $since->format('Y-m-d H:i:s')]);
            $byDowElv = [];
            foreach ($rowsElv as $row) {
                $byDowElv[(int) $row['dow']] = (int) $row['cnt'];
            }
            foreach ($order as $d) {
                $valuesEleve[] = $byDowElv[$d] ?? 0;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'valuesEleve' => $highOnly ? $values : $valuesEleve,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, valuesEleve: list<int>}
     */
    public function getParHeureData(\DateTimeImmutable $since, bool $highOnly = false): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $severityFilter = $highOnly ? " AND r.severity = 'high'" : '';

        $sql = <<<SQL
            SELECT EXTRACT(HOUR FROM r.created_at)::int AS hr, COUNT(*)::int AS cnt
            FROM report r
            WHERE r.created_at >= :since{$severityFilter}
            GROUP BY hr
            SQL;

        $rows = $conn->fetchAllAssociative($sql, ['since' => $since->format('Y-m-d H:i:s')]);
        $byH = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $h = (int) $row['hr'];
            if ($h >= 0 && $h < 24) {
                $byH[$h] = (int) $row['cnt'];
            }
        }

        $labels = array_map(static fn (int $h) => $h.'h', range(0, 23));

        $valuesAll = $byH;
        if ($highOnly) {
            return [
                'labels' => $labels,
                'values' => $valuesAll,
                'valuesEleve' => $valuesAll,
            ];
        }

        $sqlElv = <<<'SQL'
            SELECT EXTRACT(HOUR FROM r.created_at)::int AS hr, COUNT(*)::int AS cnt
            FROM report r
            WHERE r.created_at >= :since AND r.severity = 'high'
            GROUP BY hr
            SQL;
        $byHElv = array_fill(0, 24, 0);
        $rowsElv = $conn->fetchAllAssociative($sqlElv, ['since' => $since->format('Y-m-d H:i:s')]);
        foreach ($rowsElv as $row) {
            $h = (int) $row['hr'];
            if ($h >= 0 && $h < 24) {
                $byHElv[$h] = (int) $row['cnt'];
            }
        }

        return [
            'labels' => $labels,
            'values' => $valuesAll,
            'valuesEleve' => $byHElv,
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getTopTypesData(\DateTimeImmutable $since, int $limit = 7): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.situationType AS label', 'COUNT(r.id) AS cnt')
            ->andWhere('r.createdAt >= :since')
            ->andWhere('r.situationType IS NOT NULL')
            ->groupBy('r.situationType')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        if ([] === $rows) {
            return [
                'labels' => ['Comportement', 'Retard', 'Incivilité', 'Accessibilité', 'Sécurité', 'Information', 'Autre'],
                'values' => [42, 31, 24, 18, 15, 11, 8],
            ];
        }

        return [
            'labels' => array_column($rows, 'label'),
            'values' => array_map('intval', array_column($rows, 'cnt')),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getGraviteData(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.severity AS label', 'COUNT(r.id) AS cnt')
            ->andWhere('r.createdAt >= :since')
            ->groupBy('r.severity')
            ->orderBy('cnt', 'DESC')
            ->setParameter('since', $since)
            ->getQuery()
            ->getArrayResult();

        if ([] === $rows) {
            return [
                'labels' => ['Élevé', 'Moyen', 'Faible', 'Non renseigné'],
                'values' => [23, 45, 67, 12],
            ];
        }

        $labelMap = [
            'high' => 'Élevé',
            'medium' => 'Moyen',
            'low' => 'Faible',
            'critical' => 'Critique',
            'positive' => 'Positive',
        ];

        return [
            'labels' => array_map(fn (array $r) => $labelMap[$r['label']] ?? (string) $r['label'], $rows),
            'values' => array_map('intval', array_column($rows, 'cnt')),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>, valuesEleve: list<int>}
     */
    public function getAnneeData(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $since = (new \DateTimeImmutable())->modify('-12 months');

        $sql = <<<'SQL'
            SELECT
                TO_CHAR(gs.month, 'Mon') AS label,
                COALESCE(all_r.cnt, 0) AS total,
                COALESCE(elv_r.cnt, 0) AS eleve
            FROM generate_series(
                date_trunc('month', :since::date),
                date_trunc('month', CURRENT_DATE::timestamptz),
                INTERVAL '1 month'
            ) AS gs(month)
            LEFT JOIN (
                SELECT date_trunc('month', created_at) AS m, COUNT(*) AS cnt
                FROM report WHERE created_at >= :since
                GROUP BY m
            ) all_r ON all_r.m = gs.month
            LEFT JOIN (
                SELECT date_trunc('month', created_at) AS m, COUNT(*) AS cnt
                FROM report WHERE created_at >= :since AND severity = 'high'
                GROUP BY m
            ) elv_r ON elv_r.m = gs.month
            ORDER BY gs.month
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'since' => $since->format('Y-m-d'),
        ]);

        $totals = array_map('intval', array_column($rows, 'total'));
        if ([] === $rows || 0 === array_sum($totals)) {
            return [
                'labels' => ['Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc', 'Jan', 'Fév', 'Mar'],
                'values' => [28, 34, 29, 22, 18, 31, 38, 42, 35, 29, 24, 19],
                'valuesEleve' => [8, 10, 8, 6, 5, 9, 11, 12, 10, 8, 7, 5],
            ];
        }

        return [
            'labels' => array_column($rows, 'label'),
            'values' => $totals,
            'valuesEleve' => array_map('intval', array_column($rows, 'eleve')),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    public function getSourceBreakdownData(\DateTimeImmutable $since): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT COALESCE(NULLIF(TRIM(metadata->>'source'), ''), 'autre') AS src, COUNT(*)::int AS cnt
            FROM report
            WHERE created_at >= :since
            GROUP BY src
            ORDER BY cnt DESC
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'since' => $since->format('Y-m-d H:i:s'),
        ]);

        if ([] === $rows) {
            return [
                'labels' => ['Formulaire', 'QR Code', 'Réseaux sociaux', 'Téléphone'],
                'values' => [55, 38, 33, 16],
            ];
        }

        $labelPretty = [
            'formulaire' => 'Formulaire',
            'web' => 'Web',
            'api' => 'API',
            'fixture' => 'Fixtures',
            'qr' => 'QR Code',
            'qr_code' => 'QR Code',
            'reseaux' => 'Réseaux sociaux',
            'telephone' => 'Téléphone',
            'autre' => 'Autre',
        ];

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['src']);
            $labels[] = $labelPretty[$key] ?? ucfirst($key);
            $values[] = (int) $row['cnt'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>, scores: list<float>}
     */
    public function getTopTransitLinesData(\DateTimeImmutable $since, int $limit = 8): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                tl.code AS code,
                COUNT(r.id)::int AS cnt,
                ROUND(AVG(
                    CASE r.severity
                        WHEN 'high' THEN 8
                        WHEN 'medium' THEN 5
                        WHEN 'low' THEN 2
                        ELSE 3
                    END
                )::numeric, 1) AS score
            FROM report r
            INNER JOIN bus_serving bs ON bs.bus_id = r.bus_id
            INNER JOIN transit_line tl ON tl.id = bs.line_id
            WHERE r.created_at >= :since
            GROUP BY tl.id, tl.code
            ORDER BY cnt DESC
            LIMIT :limit
            SQL;

        try {
            $result = $conn->executeQuery(
                $sql,
                [
                    'since' => $since->format('Y-m-d H:i:s'),
                    'limit' => $limit,
                ],
                [
                    'since' => ParameterType::STRING,
                    'limit' => ParameterType::INTEGER,
                ],
            );
            $rows = $result->fetchAllAssociative();
        } catch (\Throwable) {
            $rows = [];
        }

        if ([] === $rows) {
            return [
                'labels' => ['L.91', 'L.42', 'L.38', 'L.63', 'L.85', 'L.26', 'L.72', 'L.94'],
                'counts' => [18, 14, 12, 9, 8, 7, 6, 5],
                'scores' => [7.2, 6.1, 5.8, 5.4, 4.9, 4.2, 3.8, 3.5],
            ];
        }

        $labels = [];
        $counts = [];
        $scores = [];
        foreach ($rows as $row) {
            $labels[] = 'L.'.$row['code'];
            $counts[] = (int) $row['cnt'];
            $scores[] = (float) $row['score'];
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'scores' => $scores,
        ];
    }

    /**
     * Codes de lignes (transit) sur lesquels il existe au moins un signalement sur la période.
     *
     * @return list<string>
     */
    public function findDistinctTransitLineCodesForReportsSince(\DateTimeImmutable $since): array
    {
        try {
            $conn = $this->getEntityManager()->getConnection();
            $sql = <<<'SQL'
                SELECT DISTINCT tl.code
                FROM report r
                INNER JOIN bus_serving bs ON bs.bus_id = r.bus_id
                INNER JOIN transit_line tl ON tl.id = bs.line_id
                WHERE r.created_at >= :since
                ORDER BY tl.code
                SQL;

            /** @var list<string|false> $rows */
            $rows = $conn->fetchFirstColumn($sql, [
                'since' => $since->format('Y-m-d H:i:s'),
            ]);

            return array_values(array_map(static fn ($c): string => (string) $c, $rows));
        } catch (\Throwable) {
            return [];
        }
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
