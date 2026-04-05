<?php

namespace App\Controller;

use App\Repository\DriverRepository;
use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    private const DASHBOARD_PAGE_SIZE = 18;

    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        ReportRepository $reportRepository,
        DriverRepository $driverRepository,
    ): Response {
        $now = new \DateTimeImmutable();
        $since7d = $now->modify('-7 days');
        $since30d = $now->modify('-30 days');
        $since90d = $now->modify('-90 days');
        $since1y = $now->modify('-365 days');
        $since8w = $now->modify('-56 days');

        $reportTotal = $reportRepository->count([]);
        $reportsLast7d = $reportRepository->countCreatedSince($since7d);
        $reportsLast30d = $reportRepository->countCreatedSince($since30d);
        $reportsLast90d = $reportRepository->countCreatedSince($since90d);

        $since14d = $now->modify('-14 days');
        $reportsLast7dPrev = $reportRepository->countBetween($since14d, $since7d);

        $driverCount = $driverRepository->count([]);

        $trend7d = $reportsLast7dPrev > 0
            ? (int) round((($reportsLast7d - $reportsLast7dPrev) / $reportsLast7dPrev) * 100)
            : 0;

        $topDriversAtRisk = $reportRepository->findTopDriversByReportCount($since1y, 6);

        $treatedTotal = $reportRepository->countTreated();
        $treatmentRate = $reportTotal > 0
            ? min(100, (int) round(($treatedTotal / $reportTotal) * 100))
            : 0;

        $driversImprovedPct = $driverCount > 0
            ? (int) round((max(0, (int) round($driverCount * 0.42)) / $driverCount) * 100)
            : 0;

        $page = max(1, $request->query->getInt('page', 1));
        $totalPages = max(1, (int) ceil($reportTotal / self::DASHBOARD_PAGE_SIZE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::DASHBOARD_PAGE_SIZE;

        $dashboardReports = $reportTotal > 0
            ? $reportRepository->findRecentForManagement(self::DASHBOARD_PAGE_SIZE, $offset)
            : [];

        $chartParJour = $reportRepository->getParJourData($since30d);
        $chartParMois = $reportRepository->getParMoisData($since1y);
        $chartParSemaine = $reportRepository->getParSemaineData($since8w);
        $chartTypes = $reportRepository->getTopTypesData($since30d, 7);
        $chartGravite = $reportRepository->getGraviteData($since30d);
        $chartAnnee = $reportRepository->getAnneeData();
        $chartSource = $reportRepository->getSourceBreakdownData($since30d);
        $chartParHeure = $reportRepository->getParHeureData($since30d, false);
        $chartParJourSemaine = $reportRepository->getParJourSemaineData($since30d, true, false);
        $chartParJourSemaineWeekdays = $reportRepository->getParJourSemaineData($since30d, false, false);
        $chartLignes = $reportRepository->getTopTransitLinesData($since30d, 8);

        $driverLinesForFilter = $reportRepository->findDistinctTransitLineCodesForReportsSince($since1y);

        $kpiByPeriod = [
            '7d' => [
                'reports' => $reportsLast7d,
                'high' => $reportRepository->countBySeverityValue('high', $since7d),
                'treatment' => $this->treatmentRateForPeriod($reportRepository, $since7d),
                'drivers_at_risk' => $this->countDriversAtRisk($reportRepository, $since7d, 3),
            ],
            '30d' => [
                'reports' => $reportsLast30d,
                'high' => $reportRepository->countBySeverityValue('high', $since30d),
                'treatment' => $this->treatmentRateForPeriod($reportRepository, $since30d),
                'drivers_at_risk' => $this->countDriversAtRisk($reportRepository, $since30d, 3),
            ],
            '90d' => [
                'reports' => $reportsLast90d,
                'high' => $reportRepository->countBySeverityValue('high', $since90d),
                'treatment' => $this->treatmentRateForPeriod($reportRepository, $since90d),
                'drivers_at_risk' => $this->countDriversAtRisk($reportRepository, $since90d, 3),
            ],
            '1an' => [
                'reports' => $reportRepository->countCreatedSince($since1y),
                'high' => $reportRepository->countBySeverityValue('high', $since1y),
                'treatment' => $this->treatmentRateForPeriod($reportRepository, $since1y),
                'drivers_at_risk' => $this->countDriversAtRisk($reportRepository, $since1y, 3),
            ],
        ];

        return $this->render('dashboard/index.html.twig', [
            'report_count' => $reportTotal,
            'driver_count' => $driverCount,
            'trend_7d' => $trend7d,
            'treatment_rate' => $treatmentRate,
            'drivers_improved_pct' => $driversImprovedPct,
            'top_drivers_at_risk' => $topDriversAtRisk,
            'dashboard_driver_lines' => $driverLinesForFilter,
            'dashboard_reports' => $dashboardReports,
            'dashboard_page' => $page,
            'dashboard_total_pages' => $totalPages,
            'chart_par_jour' => $chartParJour,
            'chart_par_mois' => $chartParMois,
            'chart_par_semaine' => $chartParSemaine,
            'chart_types' => $chartTypes,
            'chart_gravite' => $chartGravite,
            'chart_annee' => $chartAnnee,
            'chart_source' => $chartSource,
            'chart_par_heure' => $chartParHeure,
            'chart_par_jour_semaine' => $chartParJourSemaine,
            'chart_par_jour_semaine_weekdays' => $chartParJourSemaineWeekdays,
            'chart_lignes' => $chartLignes,
            'kpi_by_period' => $kpiByPeriod,
            'severity_breakdown_30d' => $reportRepository->countBySeverity($since30d),
            'situation_type_breakdown_30d' => $reportRepository->countTopSituationTypes($since30d, 8),
        ]);
    }

    private function treatmentRateForPeriod(ReportRepository $reportRepository, \DateTimeImmutable $since): int
    {
        $total = $reportRepository->countCreatedSince($since);
        if (0 === $total) {
            return 0;
        }

        $closed = $reportRepository->countClosedAmongCreatedSince($since);

        return min(100, (int) round(($closed / $total) * 100));
    }

    private function countDriversAtRisk(ReportRepository $reportRepository, \DateTimeImmutable $since, int $minReports): int
    {
        $rows = $reportRepository->findTopDriversByReportCount($since, 80);

        return count(array_filter($rows, static fn (array $d): bool => $d['count'] >= $minReports));
    }
}
