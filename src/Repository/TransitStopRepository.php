<?php

namespace App\Repository;

use App\Entity\TransitStop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransitStop>
 */
class TransitStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransitStop::class);
    }

    public function findOneByCode(string $code): ?TransitStop
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findOneByCodeInsensitive(string $code): ?TransitStop
    {
        $c = trim($code);
        if ('' === $c) {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.code) = LOWER(:c)')
            ->setParameter('c', $c)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TransitStop>
     */
    public function searchSuggestions(string $query, int $limit = 12): array
    {
        $q = trim($query);
        if ('' === $q) {
            return $this->createQueryBuilder('s')
                ->orderBy('s.name', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        $needle = '%'.mb_strtolower($q).'%';

        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.code) LIKE :q OR LOWER(s.name) LIKE :q')
            ->setParameter('q', $needle)
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRandom(): ?TransitStop
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM transit_stop ORDER BY RANDOM() LIMIT 1'
        )->fetchOne();

        if (false === $result || null === $result) {
            return null;
        }

        return $this->find((int) $result);
    }
}
