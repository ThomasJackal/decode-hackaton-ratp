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

        $reportTotal = $reportRepository->count([]);
        $page = max(1, $request->query->getInt('page', 1));
        $totalPages = max(1, (int) ceil($reportTotal / self::DASHBOARD_PAGE_SIZE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::DASHBOARD_PAGE_SIZE;

        $dashboardReports = $reportTotal > 0
            ? $reportRepository->findRecentForManagement(self::DASHBOARD_PAGE_SIZE, $offset)
            : [];

        return $this->render('dashboard/index.html.twig', [
            'report_count' => $reportTotal,
            'driver_count' => $driverRepository->count([]),
            'reports_last_7_days' => $reportRepository->countCreatedSince($since7d),
            'reports_last_30_days' => $reportRepository->countCreatedSince($since30d),
            'severity_breakdown_30d' => $reportRepository->countBySeverity($since30d),
            'situation_type_breakdown_30d' => $reportRepository->countTopSituationTypes($since30d, 8),
            'dashboard_reports' => $dashboardReports,
            'dashboard_page' => $page,
            'dashboard_total_pages' => $totalPages,
            'dashboard_page_size' => self::DASHBOARD_PAGE_SIZE,
        ]);
    }
}
