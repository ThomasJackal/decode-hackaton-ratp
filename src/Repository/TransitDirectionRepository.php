<?php

namespace App\Repository;

use App\Entity\TransitDirection;
use App\Entity\TransitLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransitDirection>
 */
class TransitDirectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransitDirection::class);
    }

    public function findOneByLineAndCode(TransitLine $line, string $code): ?TransitDirection
    {
        return $this->findOneBy(['line' => $line, 'code' => $code]);
    }

    public function findOneByLineAndLabelInsensitive(TransitLine $line, string $label): ?TransitDirection
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.line = :line')
            ->andWhere('LOWER(d.label) = LOWER(:label)')
            ->setParameter('line', $line)
            ->setParameter('label', trim($label))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TransitDirection>
     */
    public function searchSuggestionsForLine(TransitLine $line, string $query, int $limit = 12): array
    {
        $q = trim($query);

        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.line = :line')
            ->setParameter('line', $line)
            ->orderBy('d.code', 'ASC')
            ->setMaxResults($limit);

        if ('' !== $q) {
            $needle = '%'.mb_strtolower($q).'%';
            $qb->andWhere('LOWER(d.code) LIKE :q OR LOWER(d.label) LIKE :q')
                ->setParameter('q', $needle);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRandomForLine(TransitLine $line): ?TransitDirection
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM transit_direction WHERE line_id = :lid ORDER BY RANDOM() LIMIT 1',
            ['lid' => $line->getId()]
        )->fetchOne();

        if (false === $result || null === $result) {
            return null;
        }

        return $this->find((int) $result);
    }

    public function findRandom(): ?TransitDirection
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM transit_direction ORDER BY RANDOM() LIMIT 1'
        )->fetchOne();

        if (false === $result || null === $result) {
            return null;
        }

        return $this->find((int) $result);
    }
}
