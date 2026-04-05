<?php

namespace App\Repository;

use App\Entity\TransitLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransitLine>
 */
class TransitLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransitLine::class);
    }

    public function findOneByCode(string $code): ?TransitLine
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findOneByCodeInsensitive(string $code): ?TransitLine
    {
        $c = trim($code);
        if ('' === $c) {
            return null;
        }

        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.code) = LOWER(:c)')
            ->setParameter('c', $c)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TransitLine>
     */
    public function searchSuggestions(string $query, int $limit = 12): array
    {
        $q = trim($query);
        if ('' === $q) {
            return $this->createQueryBuilder('l')
                ->orderBy('l.code', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        $needle = '%'.mb_strtolower($q).'%';

        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.code) LIKE :q OR LOWER(l.name) LIKE :q')
            ->setParameter('q', $needle)
            ->orderBy('l.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRandom(): ?TransitLine
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM transit_line ORDER BY RANDOM() LIMIT 1'
        )->fetchOne();

        if (false === $result || null === $result) {
            return null;
        }

        return $this->find((int) $result);
    }
}
