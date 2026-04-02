<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Bus;
use App\Entity\Driver;
use App\Entity\Report;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données de démo : bus, conducteurs et signalements liés.
 */
final class AppFixtures extends Fixture
{
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
            1 => (new Driver())->setContact([
                'name' => 'Martin Dupont',
                'employeeId' => 'DRV-1001',
                'phone' => '+33 6 10 00 01 01',
            ]),
            2 => (new Driver())->setContact([
                'name' => 'Sophie Laurent',
                'employeeId' => 'DRV-1002',
                'phone' => '+33 6 10 00 02 02',
            ]),
            3 => (new Driver())->setContact([
                'name' => 'Karim Benali',
                'employeeId' => 'DRV-1003',
                'phone' => '+33 6 10 00 03 03',
            ]),
            4 => (new Driver())->setContact([
                'name' => 'Julie Moreau',
                'employeeId' => 'DRV-1004',
                'phone' => '+33 6 10 00 04 04',
            ]),
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
                'reporterContact' => ['name' => 'Alice P.', 'email' => 'alice.fixture@example.com', 'telephone' => ''],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Ligne 42, arrêt République, direction Nord.',
                'situationSummary' => 'Signalement sécurité : freinage soudain.',
                'reportCredibility' => 'medium',
                'metadata' => ['source' => 'fixture', 'channel' => 'web'],
            ],
            [
                'description' => 'Climatisation très faible, wagon surtout en partie arrière.',
                'severity' => 'low',
                'situationType' => 'comfort',
                'driver' => $drivers[1],
                'bus' => $buses[2],
                'reportDate' => $now->modify('-1 day 17:40'),
                'createdAt' => $now->modify('-1 day 17:45'),
                'reporterContact' => ['name' => 'Bob M.', 'email' => 'bob.fixture@example.com', 'telephone' => '+33600000001'],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Fort ensoleillement, plein été.',
                'situationSummary' => 'Confort thermique insuffisant.',
                'reportCredibility' => 'high',
                'metadata' => ['source' => 'fixture', 'channel' => 'api'],
            ],
            [
                'description' => 'Conducteur très courtois, a aidé une personne à mobilité réduite à descendre.',
                'severity' => 'low',
                'situationType' => 'positive',
                'driver' => $drivers[2],
                'bus' => $buses[3],
                'reportDate' => $now->modify('-3 days 09:05'),
                'createdAt' => $now->modify('-3 days 09:10'),
                'reporterContact' => ['name' => 'Chloé D.', 'email' => 'chloe.fixture@example.com', 'telephone' => ''],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Arrêt Gare Centre.',
                'situationSummary' => 'Félicitations pour le professionnalisme.',
                'reportCredibility' => 'high',
                'metadata' => ['source' => 'fixture'],
            ],
            [
                'description' => 'Phare avant droit grillé, visibilité réduite de nuit côté trottoir.',
                'severity' => 'medium',
                'situationType' => 'maintenance',
                'driver' => $drivers[3],
                'bus' => $buses[4],
                'reportDate' => $now->modify('-5 hours'),
                'createdAt' => $now->modify('-4 hours 55 minutes'),
                'reporterContact' => ['name' => 'David L.', 'email' => 'david.fixture@example.com', 'telephone' => ''],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Observation en soirée.',
                'situationSummary' => 'Défaut d’éclairage à signaler à la maintenance.',
                'reportCredibility' => 'medium',
                'metadata' => ['source' => 'fixture', 'priority' => 'normal'],
            ],
            [
                'description' => 'Dépassement un peu serré d’un cycliste ; sensation d’insécurité.',
                'severity' => 'high',
                'situationType' => 'safety',
                'driver' => $drivers[4],
                'bus' => $buses[5],
                'reportDate' => $now->modify('-12 hours'),
                'createdAt' => $now->modify('-11 hours 50 minutes'),
                'reporterContact' => ['name' => 'Emma R.', 'email' => 'emma.fixture@example.com', 'telephone' => '+33600000002'],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Carrefour avec piste cyclable.',
                'situationSummary' => 'Rapprochement avec vélo : à analyser.',
                'reportCredibility' => 'low',
                'metadata' => ['source' => 'fixture', 'needsReview' => true],
            ],
            [
                'description' => 'Retard annoncé d’environ 8 minutes sans message dans le véhicule.',
                'severity' => 'medium',
                'situationType' => 'punctuality',
                'driver' => $drivers[2],
                'bus' => $buses[1],
                'reportDate' => $now->modify('-7 days 07:30'),
                'createdAt' => $now->modify('-7 days 07:35'),
                'reporterContact' => ['name' => 'Franck T.', 'email' => 'franck.fixture@example.com', 'telephone' => ''],
                'aggravatingContext' => '',
                'mitigatingContext' => 'Heure de pointe matinale.',
                'situationSummary' => 'Information voyageurs à améliorer.',
                'reportCredibility' => 'medium',
                'metadata' => ['source' => 'fixture'],
            ],
        ];

        foreach ($reportsData as $row) {
            $report = (new Report())
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

            $manager->persist($report);
        }

        $manager->flush();
    }
}
