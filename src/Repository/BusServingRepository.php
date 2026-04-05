<?php

namespace App\Repository;

use App\Entity\BusServing;
use App\Entity\TransitDirection;
use App\Entity\TransitLine;
use App\Entity\TransitStop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusServing>
 */
class BusServingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusServing::class);
    }

    public function findOneByLineStopAndDirection(
        TransitLine $line,
        TransitStop $stop,
        TransitDirection $direction,
    ): ?BusServing {
        return $this->findOneBy([
            'line' => $line,
            'stop' => $stop,
            'direction' => $direction,
        ]);
    }

    public function findRandomByBusId(int $busId): ?BusServing
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM bus_serving WHERE bus_id = :bid ORDER BY RANDOM() LIMIT 1',
            ['bid' => $busId]
        )->fetchOne();

        if (false === $result || null === $result) {
            return null;
        }

        return $this->find((int) $result);
    }

    public function findRandom(): ?BusServing
    {
        $result = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT id FROM bus_serving ORDER BY RANDOM() LIMIT 1'
        )->fetchOne();

        if (false === $result || null === $result) {
            return null;
        }

        return $this->find((int) $result);
    }
}
