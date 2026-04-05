<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Bus;
use App\Entity\Driver;
use App\Entity\Report;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données de démo : bus, conducteurs, signalements artisanaux puis volume pour graphiques / dashboard.
 */
final class AppFixtures extends Fixture
{
    private const BULK_REPORTS = 174;

    public function load(ObjectManager $manager): void
    {
        $busRepo = $manager->getRepository(Bus::class);
        if ($busRepo->count([]) > 0) {
            return;
        }

        $buses = [];
        foreach (range(1, 5) as $i) {
            $bus = new Bus();
            $manager->persist($bus);
            $buses[$i] = $bus;
        }

        $drivers = [
            1 => (new Driver())->setContact(['telephone' => '+33 6 10 00 01 01']),
            2 => (new Driver())->setContact(['telephone' => '+33 6 10 00 02 02']),
            3 => (new Driver())->setContact(['telephone' => '+33 6 10 00 03 03']),
            4 => (new Driver())->setContact(['telephone' => '+33 6 10 00 04 04']),
        ];

        foreach ($drivers as $driver) {
            $manager->persist($driver);
        }

        $manager->flush();

        $now = new \DateTimeImmutable('2026-04-01 12:00:00', new \DateTimeZone('Europe/Paris'));

        $reportsData = [
            [
                'description' => 'Freinage brutal à l’approche de l’arrêt ; plusieurs passagers ont failli tomber.',
                'severity' => 'high',
                'situationType' => 'safety',
                'driver' => $drivers[1],
                'bus' => $buses[1],
                'reportDate' => $now->modify('-2 days 08:15'),
                'createdAt' => $now->modify('-2 days 08:20'),
                'reporterContact' => ['email' => 'alice.fixture@example.com', 'telephone' => ''],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Ligne 42, arrêt République, direction Nord.',
                'situationSummary' => 'Signalement sécurité : freinage soudain.',
                'reportCredibility' => 'medium',
                'transitKey' => 'l42_rep_a',
            ],
            [
                'description' => 'Climatisation très faible, wagon surtout en partie arrière.',
                'severity' => 'low',
                'situationType' => 'comfort',
                'driver' => $drivers[1],
                'bus' => $buses[2],
                'reportDate' => $now->modify('-1 day 17:40'),
                'createdAt' => $now->modify('-1 day 17:45'),
                'reporterContact' => ['email' => '', 'telephone' => '+33600000001'],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Fort ensoleillement, plein été.',
                'situationSummary' => 'Confort thermique insuffisant.',
                'reportCredibility' => 'high',
                'transitKey' => 'l91_opera_b',
            ],
            [
                'description' => 'Conducteur très courtois, a aidé une personne à mobilité réduite à descendre.',
                'severity' => 'low',
                'situationType' => 'positive',
                'driver' => $drivers[2],
                'bus' => $buses[3],
                'reportDate' => $now->modify('-3 days 09:05'),
                'createdAt' => $now->modify('-3 days 09:10'),
                'reporterContact' => ['email' => 'chloe.fixture@example.com', 'telephone' => '+33600000003'],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Arrêt Gare Centre.',
                'situationSummary' => 'Félicitations pour le professionnalisme.',
                'reportCredibility' => 'high',
                'transitKey' => 'l42_gare_b',
            ],
            [
                'description' => 'Phare avant droit grillé, visibilité réduite de nuit côté trottoir.',
                'severity' => 'medium',
                'situationType' => 'maintenance',
                'driver' => $drivers[3],
                'bus' => $buses[4],
                'reportDate' => $now->modify('-5 hours'),
                'createdAt' => $now->modify('-4 hours 55 minutes'),
                'reporterContact' => ['email' => 'david.fixture@example.com', 'telephone' => ''],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Observation en soirée.',
                'situationSummary' => 'Défaut d’éclairage à signaler à la maintenance.',
                'reportCredibility' => 'medium',
                'transitKey' => 'l91_rep_b',
            ],
            [
                'description' => 'Dépassement un peu serré d’un cycliste ; sensation d’insécurité.',
                'severity' => 'high',
                'situationType' => 'safety',
                'driver' => $drivers[4],
                'bus' => $buses[5],
                'reportDate' => $now->modify('-12 hours'),
                'createdAt' => $now->modify('-11 hours 50 minutes'),
                'reporterContact' => ['email' => 'emma.fixture@example.com', 'telephone' => '+33600000002'],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Carrefour avec piste cyclable.',
                'situationSummary' => 'Rapprochement avec vélo : à analyser.',
                'reportCredibility' => 'low',
                'transitKey' => 'l42_opera_a',
            ],
            [
                'description' => 'Retard annoncé d’environ 8 minutes sans message dans le véhicule.',
                'severity' => 'medium',
                'situationType' => 'punctuality',
                'driver' => $drivers[2],
                'bus' => $buses[1],
                'reportDate' => $now->modify('-7 days 07:30'),
                'createdAt' => $now->modify('-7 days 07:35'),
                'reporterContact' => ['email' => '', 'telephone' => '+33600000004'],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Heure de pointe matinale.',
                'situationSummary' => 'Information voyageurs à améliorer.',
                'reportCredibility' => 'medium',
                'transitKey' => 'l91_opera_a',
            ],
        ];

        foreach ($reportsData as $row) {
            $transit = $this->transitSnapshot($row['transitKey']);
            unset($row['transitKey']);
            $row['metadata'] = $this->metadataFormulaire(
                $transit,
                $this->finderFixtureMeta($transit, $row['bus'], $row['driver']),
            );
            $manager->persist($this->makeReport($row));
        }

        $this->persistBulkReports($manager, $drivers, $buses, $now, self::BULK_REPORTS);

        $manager->flush();
    }

    /**
     * @param array<int, Driver> $drivers
     * @param array<int, Bus>    $buses
     */
    private function persistBulkReports(
        ObjectManager $manager,
        array $drivers,
        array $buses,
        \DateTimeImmutable $anchor,
        int $count,
    ): void {
        $driversList = array_values($drivers);
        $busesList = array_values($buses);
        $nDrivers = \count($driversList);
        $nBuses = \count($busesList);

        $transitKeys = ['l42_rep_a', 'l42_gare_b', 'l91_opera_a', 'l91_rep_b', 'l42_opera_b'];

        $severities = ['high', 'high', 'medium', 'medium', 'medium', 'low', 'low', 'low'];
        $situationTypes = ['safety', 'punctuality', 'comfort', 'maintenance', 'information', 'safety', 'punctuality', 'other'];
        $descriptions = [
            'Signalement lié au freinage ou à un mouvement brusque à bord.',
            'Retard significatif sans annonce claire dans le véhicule.',
            'Comportement ou propos déplaisant envers un voyageur.',
            'Équipement ou accès PMR défaillant ou inutilisable.',
            'Manque d’information sur l’itinéraire ou les arrêts.',
            'Vitesse ou manœuvre perçue comme dangereuse.',
            'Affluence et conditions de voyage difficiles à bord.',
            'Autre incident signalé par un voyageur.',
        ];

        for ($i = 1; $i <= $count; ++$i) {
            if (0 === $i % 7) {
                $di = 0;
            } elseif (0 === $i % 5) {
                $di = min(1, $nDrivers - 1);
            } elseif (0 === $i % 4) {
                $di = min(2, $nDrivers - 1);
            } else {
                $di = random_int(0, $nDrivers - 1);
            }
            $driver = $driversList[$di];
            $bus = $busesList[random_int(0, $nBuses - 1)];

            $randomDate = $anchor
                ->modify('-'.random_int(0, 365).' days')
                ->modify('-'.random_int(0, 23).' hours')
                ->modify('-'.random_int(0, 59).' minutes');

            $idx = random_int(0, 7);
            $severity = $severities[$idx];
            $situationType = $situationTypes[$idx];
            $description = $descriptions[$idx];
            $cred = 'high' === $severity || 'medium' === $severity ? 'medium' : 'low';

            $transitKey = $transitKeys[$i % \count($transitKeys)];
            $transit = $this->transitSnapshot($transitKey);

            $email = '';
            $tel = '';
            $mod = $i % 3;
            if (0 === $mod) {
                $email = 'voyageur'.$i.'.fixture@example.com';
            } elseif (1 === $mod) {
                $tel = '+336'.str_pad((string) ($i % 100000000), 8, '0', STR_PAD_LEFT);
            } else {
                $email = 'mix'.$i.'@fixture.example.com';
                $tel = '+336'.str_pad((string) (($i * 7) % 100000000), 8, '0', STR_PAD_LEFT);
            }

            $row = [
                'description' => $description,
                'severity' => $severity,
                'situationType' => $situationType,
                'driver' => $driver,
                'bus' => $bus,
                'reportDate' => $randomDate,
                'createdAt' => $randomDate,
                'reporterContact' => [
                    'email' => $email,
                    'telephone' => $tel,
                ],
                'aggravatingContext' => 'high' === $severity ? 'Contexte aggravant signalé.' : '',
                'mitigatingContext' => random_int(0, 10) > 6 ? 'Forte affluence, conditions de circulation difficiles.' : '',
                'situationSummary' => 'Signalement du '.$randomDate->format('d/m/Y').' — '.$situationType,
                'reportCredibility' => $cred,
                'metadata' => $this->metadataFormulaire(
                    $transit,
                    $this->finderFixtureMeta($transit, $bus, $driver),
                ),
            ];

            $report = $this->makeReport($row);
            if (random_int(1, 100) <= 72) {
                $report->setClosedAt($randomDate->modify('+'.random_int(1, 72).' hours'));
            }
            $manager->persist($report);

            if (0 === $i % 50) {
                $manager->flush();
            }
        }
    }

    /**
     * @return array{line: array{id: int|null, code: string, name: string}, stop: array{id: int|null, code: string, name: string}, direction: array{id: int|null, code: string, label: string, line_id: int|null}}
     */
    private function transitSnapshot(string $key): array
    {
        $presets = [
            'l42_rep_a' => ['42', 'REP', 'République', 'A', 'Vers Opéra'],
            'l42_gare_b' => ['42', 'GARE', 'Gare du Nord', 'B', 'Vers Porte d’Orléans'],
            'l42_opera_a' => ['42', 'OPERA', 'Opéra', 'A', 'Vers Opéra'],
            'l42_opera_b' => ['42', 'OPERA', 'Opéra', 'B', 'Vers Porte d’Orléans'],
            'l91_opera_a' => ['91', 'OPERA', 'Opéra', 'A', 'Vers Bastille'],
            'l91_opera_b' => ['91', 'OPERA', 'Opéra', 'B', 'Vers Montparnasse'],
            'l91_rep_b' => ['91', 'REP', 'République', 'B', 'Vers Montparnasse'],
        ];

        [$lineCode, $stopCode, $stopName, $dirCode, $dirLabel] = $presets[$key] ?? $presets['l42_rep_a'];

        return [
            'line' => ['id' => null, 'code' => $lineCode, 'name' => 'Ligne '.$lineCode.' (démo)'],
            'stop' => ['id' => null, 'code' => $stopCode, 'name' => $stopName],
            'direction' => ['id' => null, 'code' => $dirCode, 'label' => $dirLabel, 'line_id' => null],
        ];
    }

    /**
     * @param array{line: array, stop: array, direction: array} $transit
     *
     * @return array<string, mixed>
     */
    private function finderFixtureMeta(array $transit, Bus $bus, Driver $driver): array
    {
        return [
            'source' => 'line_and_stop',
            'lineId' => $transit['line']['code'],
            'stopId' => $transit['stop']['code'],
            'direction' => $transit['direction']['code'],
            'transit' => $transit,
            'bus' => ['busId' => (string) $bus->getId()],
            'driver' => ['driverId' => (string) $driver->getId()],
        ];
    }

    /**
     * @param array{line: array, stop: array, direction: array} $transit
     * @param array<string, mixed>                             $finderMeta
     *
     * @return array<string, mixed>
     */
    private function metadataFormulaire(array $transit, array $finderMeta): array
    {
        return [
            'transit' => $transit,
            'line' => $transit['line'],
            'stop' => $transit['stop'],
            'direction' => $transit['direction'],
            'finder' => $finderMeta,
            'source' => 'formulaire',
        ];
    }

    /**
     * @param array{
     *     description: string,
     *     severity: string,
     *     situationType: string,
     *     driver: Driver,
     *     bus: Bus,
     *     reportDate: \DateTimeImmutable,
     *     createdAt: \DateTimeImmutable,
     *     reporterContact: array<string, string>,
     *     aggravatingContext: string,
     *     mitigatingContext: string,
     *     situationSummary: string,
     *     reportCredibility: string,
     *     metadata: array<string, mixed>
     * } $row
     */
    private function makeReport(array $row): Report
    {
        return (new Report())
            ->setDescription($row['description'])
            ->setSeverity($row['severity'])
            ->setSituationType($row['situationType'])
            ->setDriver($row['driver'])
            ->setBus($row['bus'])
            ->setReportDate($row['reportDate'])
            ->setIncidentDate($row['reportDate'])
            ->setCreatedAt($row['createdAt'])
            ->setReporterContact($row['reporterContact'])
            ->setAggravatingContext($row['aggravatingContext'])
            ->setMitigatingContext($row['mitigatingContext'])
            ->setSituationSummary($row['situationSummary'])
            ->setReportCredibility($row['reportCredibility'])
            ->setMetadata($row['metadata']);
    }
}
