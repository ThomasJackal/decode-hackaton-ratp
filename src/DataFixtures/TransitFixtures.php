<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Bus;
use App\Entity\BusServing;
use App\Entity\TransitDirection;
use App\Entity\TransitLine;
use App\Entity\TransitStop;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Référentiel transit démo : lignes, arrêts, directions et affectations bus (bus_serving).
 */
final class TransitFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        if ($manager->getRepository(TransitLine::class)->count([]) > 0) {
            return;
        }

        $line42 = (new TransitLine())->setCode('42')->setName('Porte d’Orléans — Opéra');
        $line91 = (new TransitLine())->setCode('91')->setName('Montparnasse — Bastille');
        $manager->persist($line42);
        $manager->persist($line91);

        $stopRep = (new TransitStop())->setCode('REP')->setName('République');
        $stopGare = (new TransitStop())->setCode('GARE')->setName('Gare du Nord');
        $stopOpera = (new TransitStop())->setCode('OPERA')->setName('Opéra');
        $manager->persist($stopRep);
        $manager->persist($stopGare);
        $manager->persist($stopOpera);

        $d42A = (new TransitDirection())->setCode('A')->setLabel('Vers Opéra')->setLine($line42);
        $d42B = (new TransitDirection())->setCode('B')->setLabel('Vers Porte d’Orléans')->setLine($line42);
        $d91A = (new TransitDirection())->setCode('A')->setLabel('Vers Bastille')->setLine($line91);
        $d91B = (new TransitDirection())->setCode('B')->setLabel('Vers Montparnasse')->setLine($line91);
        foreach ([$d42A, $d42B, $d91A, $d91B] as $d) {
            $manager->persist($d);
        }

        $manager->flush();

        /** @var list<Bus> $buses */
        $buses = $manager->getRepository(Bus::class)->findBy([], ['id' => 'ASC'], 5);
        if ([] === $buses) {
            return;
        }

        $pairs = [
            [$buses[0], $line42, $stopRep, $d42A],
            [isset($buses[1]) ? $buses[1] : $buses[0], $line42, $stopGare, $d42B],
            [isset($buses[2]) ? $buses[2] : $buses[0], $line91, $stopOpera, $d91A],
            [isset($buses[3]) ? $buses[3] : $buses[0], $line91, $stopRep, $d91B],
        ];
        if (isset($buses[4])) {
            $pairs[] = [$buses[4], $line42, $stopOpera, $d42B];
        }

        foreach ($pairs as [$bus, $line, $stop, $dir]) {
            $row = (new BusServing())
                ->setBus($bus)
                ->setLine($line)
                ->setStop($stop)
                ->setDirection($dir);
            $manager->persist($row);
        }

        $manager->flush();
    }

    /**
     * @return list<class-string<Fixture>>
     */
    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
