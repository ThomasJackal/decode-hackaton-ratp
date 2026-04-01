<?php

namespace App\Controller;

use App\Entity\Report;
use App\Form\ReportSubmissionType;
use App\Form\ReportType;
use App\Repository\BusRepository;
use App\Repository\DriverRepository;
use App\Repository\ReportRepository;
use App\Service\Interface\FinderServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/report')]
final class ReportController extends AbstractController
{
    private const DEFAULT_WEBHOOK_URL = 'http://localhost:5678/webhook/signalement';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $reportWebhookUrl,
    ) {
    }

    private const REPORT_LIST_PAGE_SIZE = 25;

    #[Route(name: 'app_report_index', methods: ['GET'])]
    public function index(Request $request, ReportRepository $reportRepository): Response
    {
        $period = $request->query->getString('period', '');
        $since = match ($period) {
            '7d' => new \DateTimeImmutable('-7 days'),
            '30d' => new \DateTimeImmutable('-30 days'),
            default => null,
        };

        $severity = $request->query->getString('severity', '') ?: null;
        $category = $request->query->getString('category', '') ?: null;
        $driverId = $request->query->getInt('driver_id');
        $driverId = $driverId > 0 ? $driverId : null;
        $busId = $request->query->getInt('bus_id');
        $busId = $busId > 0 ? $busId : null;

        $total = $reportRepository->countManagementFiltered($since, $severity, $category, $driverId, $busId);
        $page = max(1, $request->query->getInt('page', 1));
        $totalPages = max(1, (int) ceil($total / self::REPORT_LIST_PAGE_SIZE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::REPORT_LIST_PAGE_SIZE;

        $reports = $total > 0
            ? $reportRepository->findManagementFiltered(
                $since,
                $severity,
                $category,
                $driverId,
                $busId,
                self::REPORT_LIST_PAGE_SIZE,
                $offset
            )
            : [];

        $filterQuery = array_filter([
            'period' => '' !== $period ? $period : null,
            'severity' => $severity,
            'category' => $category,
            'driver_id' => $driverId,
            'bus_id' => $busId,
        ], static fn ($v) => null !== $v && '' !== $v);

        return $this->render('report/index.html.twig', [
            'reports' => $reports,
            'report_list_total' => $total,
            'report_list_page' => $page,
            'report_list_total_pages' => $totalPages,
            'report_list_filter_query' => $filterQuery,
            'filter_period' => $period,
            'filter_severity' => $severity ?? '',
            'filter_category' => $category ?? '',
            'filter_driver_id' => $driverId ?? 0,
            'filter_bus_id' => $busId ?? 0,
            'severity_options' => $reportRepository->findDistinctSeverityValues(),
            'category_options' => $reportRepository->findDistinctCategoryValues(),
        ]);
    }

    #[Route('/new/find-bus', name: 'app_report_find_bus', methods: ['POST'])]
    public function findBus(
        Request $request,
        FinderServiceInterface $finder,
        BusRepository $busRepository,
        DriverRepository $driverRepository,
    ): JsonResponse {
        $token = $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('report_find_bus', $token)) {
            return $this->json(['ok' => false, 'error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $lineId = trim((string) ($data['lineId'] ?? ''));
        $stopId = trim((string) ($data['stopId'] ?? ''));
        $direction = trim((string) ($data['direction'] ?? ''));
        $reportDateRaw = $data['reportDate'] ?? null;

        if ('' === $lineId || '' === $stopId || '' === $direction) {
            return $this->json(['ok' => false, 'error' => 'Ligne, arrêt et direction sont obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reportDate = is_string($reportDateRaw) && '' !== trim($reportDateRaw)
                ? new \DateTimeImmutable($reportDateRaw)
                : new \DateTimeImmutable();
        } catch (\Exception) {
            return $this->json(['ok' => false, 'error' => 'Date ou heure invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $time = $reportDate->format(\DateTimeInterface::ATOM);
        $busFound = $finder->findBusByLineAndStop($lineId, $stopId, $direction, $time);
        $busId = $busFound['busId'] ?? null;

        if (!is_numeric($busId)) {
            return $this->json(
                ['ok' => false, 'error' => 'Aucun bus trouvé pour cette ligne, cet arrêt et cette direction à cette heure.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $driverFound = $finder->findDriverByBus((string) $busId, $time);
        $driverId = $driverFound['driverId'] ?? null;

        if (!is_numeric($driverId)) {
            return $this->json(
                ['ok' => false, 'error' => 'Conducteur introuvable pour ce véhicule à cette heure.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $bus = $busRepository->find((int) $busId);
        $driver = $driverRepository->find((int) $driverId);

        if (null === $bus || null === $driver) {
            return $this->json(
                ['ok' => false, 'error' => 'Le bus ou le conducteur n’existe pas dans la base.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->json([
            'ok' => true,
            'busId' => $bus->getId(),
            'driverId' => $driver->getId(),
            'finder' => [
                'lineId' => $lineId,
                'stopId' => $stopId,
                'direction' => $direction,
                'bus' => $busFound,
                'driver' => $driverFound,
            ],
        ]);
    }

    #[Route('/new', name: 'app_report_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FinderServiceInterface $finder,
        BusRepository $busRepository,
        DriverRepository $driverRepository,
    ): Response {
        $prefilledBusId = $this->resolvePrefilledBusIdFromRequest($request, $busRepository);
        $rawBusIdParam = $request->query->get('busId');
        if (null !== $rawBusIdParam && '' !== (string) $rawBusIdParam && null === $prefilledBusId) {
            $this->addFlash('warning', 'Le bus demandé dans l’URL est introuvable. Utilisez le Bus Finder ci-dessous.');
        }

        $report = new Report();
        $report->setReportDate(new \DateTimeImmutable());
        $report->setReporterContact([]);
        $report->setContext('');
        $report->setSummary('');
        $report->setMetadata([]);
        $report->setSeverity('non-renseigne');
        $report->setCategory('non-renseigne');

        $form = $this->createForm(ReportSubmissionType::class, $report, [
            'bus_identifier_from_url' => null !== $prefilledBusId,
        ]);

        if (null !== $prefilledBusId && $form->has('busIdentifier')) {
            $form->get('busIdentifier')->setData((string) $prefilledBusId);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $webhookUrl = trim($this->reportWebhookUrl) !== '' ? trim($this->reportWebhookUrl) : self::DEFAULT_WEBHOOK_URL;

            $reportDate = $report->getReportDate();
            if (null === $reportDate) {
                $form->addError(new FormError('La date du trajet est obligatoire.'));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $resolved = $this->resolveBusAndDriverFromFinder($finder, $reportDate, $form, $prefilledBusId);

            if (null === $resolved) {
                $form->addError(new FormError(
                    null !== $prefilledBusId
                        ? 'Impossible de résoudre le conducteur pour ce bus à cette heure.'
                        : 'Impossible d’associer un bus ou un conducteur. Vérifiez ligne, arrêt et direction, ou utilisez « Trouver le bus ».'
                ));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $busId = $resolved['busId'];
            $driverId = $resolved['driverId'];
            $found = $resolved['finder'];

            $bus = $busRepository->find((int) $busId);
            $driver = $driverRepository->find((int) $driverId);

            if (null === $bus || null === $driver) {
                $form->addError(new FormError('Le bus ou le conducteur résolu est introuvable en base.'));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $report->setBus($bus);
            $report->setDriver($driver);

            if (null === $report->getCreatedAt()) {
                $report->setCreatedAt(new \DateTimeImmutable());
            }

            $meta = $report->getMetadata();
            $report->setMetadata(array_merge(\is_array($meta) ? $meta : [], [
                'finder' => $found,
                'finderResolvedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]));

            $reporterEmail = trim((string) $form->get('reporterEmail')->getData());
            $reporterTelephone = trim((string) $form->get('reporterTelephone')->getData());
            $report->setReporterContact([
                'email' => $reporterEmail,
                'telephone' => $reporterTelephone,
            ]);

            $payload = [
                'driver_id' => $driver->getId(),
                'bus_id' => $bus->getId(),
                'description' => $report->getDescription(),
                'report_date' => $reportDate->format('Y-m-d H:i:s'),
                'heure_incident' => $reportDate->format('H:i'),
                'reporter_contact' => [
                    'email' => $reporterEmail,
                    'telephone' => $reporterTelephone,
                ],
            ];

            try {
                $response = $this->httpClient->request('POST', $webhookUrl, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => $payload,
                    'timeout' => 15,
                ]);
                $status = $response->getStatusCode();
                if ($status < 200 || $status >= 300) {
                    $form->addError(new FormError(
                        'Le service de signalement a renvoyé une erreur (HTTP '.$status.'). Réessayez plus tard.'
                    ));

                    return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
                }
            } catch (\Throwable $e) {
                $form->addError(new FormError(
                    'Impossible de joindre le service de signalement : '.$e->getMessage()
                ));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $entityManager->persist($report);
            $entityManager->flush();

            return $this->redirectToRoute('app_report_thanks', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
    }

    /**
     * @return array{report: Report, form: FormInterface, prefilled_bus_id: int|null}
     */
    private function newFormViewContext(FormInterface $form, Report $report, ?int $prefilledBusId): array
    {
        return [
            'report' => $report,
            'form' => $form,
            'prefilled_bus_id' => $prefilledBusId,
        ];
    }

    /**
     * @return array{busId: int, driverId: int, finder: array<string, mixed>}|null
     */
    private function resolveBusAndDriverFromFinder(
        FinderServiceInterface $finder,
        \DateTimeImmutable $reportDate,
        FormInterface $form,
        ?int $prefilledBusId,
    ): ?array {
        $time = $reportDate->format(\DateTimeInterface::ATOM);

        if (null !== $prefilledBusId) {
            $driverFound = $finder->findDriverByBus((string) $prefilledBusId, $time);
            $driverId = $driverFound['driverId'] ?? null;
            if (!is_numeric($driverId)) {
                return null;
            }

            return [
                'busId' => $prefilledBusId,
                'driverId' => (int) $driverId,
                'finder' => [
                    'source' => 'prefilled_bus',
                    'busId' => $prefilledBusId,
                    'driver' => $driverFound,
                ],
            ];
        }

        $lineId = trim((string) $form->get('lineId')->getData());
        $stopId = trim((string) $form->get('stopId')->getData());
        $direction = trim((string) $form->get('direction')->getData());

        $busFound = $finder->findBusByLineAndStop($lineId, $stopId, $direction, $time);
        $busId = $busFound['busId'] ?? null;
        if (!is_numeric($busId)) {
            return null;
        }

        $driverFound = $finder->findDriverByBus((string) $busId, $time);
        $driverId = $driverFound['driverId'] ?? null;
        if (!is_numeric($driverId)) {
            return null;
        }

        return [
            'busId' => (int) $busId,
            'driverId' => (int) $driverId,
            'finder' => [
                'source' => 'line_and_stop',
                'lineId' => $lineId,
                'stopId' => $stopId,
                'direction' => $direction,
                'bus' => $busFound,
                'driver' => $driverFound,
            ],
        ];
    }

    private function resolvePrefilledBusIdFromRequest(Request $request, BusRepository $busRepository): ?int
    {
        $raw = $request->query->get('busId');
        if (null === $raw || '' === $raw) {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $id = (int) $raw;
        if ($id < 1) {
            return null;
        }

        $bus = $busRepository->find($id);

        return null !== $bus ? $id : null;
    }

    #[Route('/thanks', name: 'app_report_thanks', methods: ['GET'])]
    public function thanks(): Response
    {
        return $this->render('report/thanks.html.twig');
    }

    #[Route('/{id}', name: 'app_report_show', methods: ['GET'])]
    public function show(Report $report): Response
    {
        return $this->render('report/show.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_report_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Report $report, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_report_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('report/edit.html.twig', [
            'report' => $report,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_report_delete', methods: ['POST'])]
    public function delete(Request $request, Report $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$report->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($report);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_report_index', [], Response::HTTP_SEE_OTHER);
    }
}
