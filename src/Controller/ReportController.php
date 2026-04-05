<?php

namespace App\Controller;

use App\Entity\Report;
use App\Entity\User;
use App\Form\ReportCloseType;
use App\Form\ReportSubmissionType;
use App\Repository\BusRepository;
use App\Repository\DriverRepository;
use App\Repository\ReportRepository;
use App\Repository\TransitDirectionRepository;
use App\Repository\TransitLineRepository;
use App\Repository\TransitStopRepository;
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
        $situationType = $request->query->getString('situation_type', '') ?: null;
        if (null === $situationType || '' === $situationType) {
            $situationType = $request->query->getString('category', '') ?: null;
        }
        $driverId = $request->query->getInt('driver_id');
        $driverId = $driverId > 0 ? $driverId : null;
        $busId = $request->query->getInt('bus_id');
        $busId = $busId > 0 ? $busId : null;

        $total = $reportRepository->countManagementFiltered($since, $severity, $situationType, $driverId, $busId);
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
                $situationType,
                $driverId,
                $busId,
                self::REPORT_LIST_PAGE_SIZE,
                $offset
            )
            : [];

        $filterQuery = array_filter([
            'period' => '' !== $period ? $period : null,
            'severity' => $severity,
            'situation_type' => $situationType,
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
            'filter_situation_type' => $situationType ?? '',
            'filter_driver_id' => $driverId ?? 0,
            'filter_bus_id' => $busId ?? 0,
            'severity_options' => $reportRepository->findDistinctSeverityValues(),
            'situation_type_options' => $reportRepository->findDistinctSituationTypeValues(),
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
                'transit' => $busFound['transit'] ?? null,
                'bus' => $busFound,
                'driver' => $driverFound,
            ],
        ]);
    }

    #[Route('/new/transit-suggest', name: 'app_report_transit_suggest', methods: ['GET'])]
    public function transitSuggest(
        Request $request,
        TransitLineRepository $transitLineRepository,
        TransitStopRepository $transitStopRepository,
        TransitDirectionRepository $transitDirectionRepository,
    ): JsonResponse {
        $field = $request->query->getString('field');
        $q = $request->query->getString('q');
        $lineCode = $request->query->getString('line');

        $suggestions = match ($field) {
            'line' => array_map(static fn ($l) => [
                'value' => $l->getCode(),
                'label' => $l->getName(),
            ], $transitLineRepository->searchSuggestions($q, 20)),
            'stop' => array_map(static fn ($s) => [
                'value' => $s->getCode(),
                'label' => $s->getName(),
            ], $transitStopRepository->searchSuggestions($q, 20)),
            'direction' => $this->directionSuggestEntries($transitLineRepository, $transitDirectionRepository, $lineCode, $q),
            default => [],
        };

        return $this->json(['suggestions' => $suggestions]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function directionSuggestEntries(
        TransitLineRepository $transitLineRepository,
        TransitDirectionRepository $transitDirectionRepository,
        string $lineCode,
        string $query,
    ): array {
        $trimLine = trim($lineCode);
        if ('' === $trimLine) {
            return [];
        }

        $line = $transitLineRepository->findOneByCode($trimLine)
            ?? $transitLineRepository->findOneByCodeInsensitive($trimLine);
        if (null === $line) {
            return [];
        }

        $directions = $transitDirectionRepository->searchSuggestionsForLine($line, $query, 20);

        return array_map(static fn ($d) => [
            'value' => $d->getCode(),
            'label' => $d->getLabel(),
        ], $directions);
    }

    #[Route('/new', name: 'app_report_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
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
        $now = new \DateTimeImmutable();
        $report->setReportDate($now);
        $report->setIncidentDate($now);
        $report->setReporterContact([]);
        $report->setAggravatingContext('');
        $report->setMitigatingContext('');
        $report->setSituationSummary('');
        $report->setReportCredibility('');
        $report->setMetadata([]);
        $report->setSeverity('non-renseigne');
        $report->setSituationType('non-renseigne');

        $form = $this->createForm(ReportSubmissionType::class, $report, [
            'bus_identifier_from_url' => null !== $prefilledBusId,
        ]);

        if (null !== $prefilledBusId && $form->has('busIdentifier')) {
            $form->get('busIdentifier')->setData((string) $prefilledBusId);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $webhookUrl = trim($this->reportWebhookUrl);
            if ('' === $webhookUrl) {
                $form->addError(new FormError(
                    'La variable d’environnement REPORT_WEBHOOK_URL doit être définie pour envoyer le signalement.'
                ));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $reportDate = $report->getReportDate();
            if (null === $reportDate) {
                $form->addError(new FormError('La date du trajet est obligatoire.'));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $report->setIncidentDate($reportDate);

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

            $bus = $busRepository->find((int) $busId);
            $driver = $driverRepository->find((int) $driverId);

            if (null === $bus || null === $driver) {
                $form->addError(new FormError('Le bus ou le conducteur résolu est introuvable en base.'));

                return $this->render('report/new.html.twig', $this->newFormViewContext($form, $report, $prefilledBusId));
            }

            $reporterEmail = trim((string) $form->get('reporterEmail')->getData());
            $reporterTelephone = trim((string) $form->get('reporterTelephone')->getData());

            $incidentDate = $report->getIncidentDate() ?? $reportDate;
            $dateFmt = 'Y-m-d H:i:s';
            $finderMeta = $resolved['finder'];
            $transit = is_array($finderMeta['transit'] ?? null) ? $finderMeta['transit'] : null;

            $payload = [
                'driver_id' => $driver->getId(),
                'bus_id' => $bus->getId(),
                'description' => (string) $report->getDescription(),
                'report_date' => $reportDate->format($dateFmt),
                'incident_date' => $incidentDate->format($dateFmt),
                'reporter_contact' => [
                    'email' => $reporterEmail,
                    'telephone' => $reporterTelephone,
                ],
                'metadata' => [
                    'transit' => $transit,
                    'line' => $transit !== null ? ($transit['line'] ?? null) : null,
                    'stop' => $transit !== null ? ($transit['stop'] ?? null) : null,
                    'direction' => $transit !== null ? ($transit['direction'] ?? null) : null,
                    'finder' => $finderMeta,
                    'source' => 'formulaire',
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
                    'transit' => $driverFound['transit'] ?? null,
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
                'transit' => $busFound['transit'] ?? null,
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

    #[Route('/{id}/closure-reason', name: 'app_report_save_closure_reason', methods: ['POST'])]
    public function saveClosureReason(
        Request $request,
        Report $report,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($report->isClosed()) {
            $this->addFlash('warning', 'Ce signalement est clos et ne peut plus être modifié.');

            return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
        }

        $form = $this->createForm(ReportCloseType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'La raison n’a pas pu être enregistrée (texte trop long).');

            return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
        }

        $reason = trim((string) $form->get('closureReason')->getData());
        if ('' === $reason) {
            $report->setClosureReason(null);
        } else {
            $report->setClosureReason($reason);
        }

        $entityManager->flush();

        $this->addFlash('success', 'La raison a été enregistrée.');

        return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
    }

    #[Route('/{id}/close', name: 'app_report_close', methods: ['POST'])]
    public function close(
        Request $request,
        Report $report,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($report->isClosed()) {
            $this->addFlash('warning', 'Ce signalement est clos et ne peut plus être modifié.');

            return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
        }

        $form = $this->createForm(ReportCloseType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'Impossible de clôturer : vérifiez le texte saisi (4000 caractères maximum).');

            return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
        }

        $reason = trim((string) $form->get('closureReason')->getData());
        if (strlen($reason) < 5) {
            $this->addFlash('danger', 'Pour clôturer, la raison doit contenir au moins 5 caractères. Utilisez « Sauvegarder » pour garder un brouillon plus court.');

            return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $report->setClosedAt(new \DateTimeImmutable());
        $report->setClosureReason($reason);
        $report->setClosedBy($user);

        $entityManager->flush();

        $this->addFlash('success', 'Le signalement a été clos.');

        return $this->redirectToRoute('app_report_show', ['id' => $report->getId()]);
    }

    #[Route('/{id}', name: 'app_report_show', methods: ['GET'])]
    public function show(Report $report, ReportRepository $reportRepository): Response
    {
        $id = (int) $report->getId();
        $detail = $reportRepository->findForManagementDetail($id);
        if (null === $detail) {
            throw $this->createNotFoundException();
        }

        $canClose = !$detail->isClosed()
            && ($this->isGranted('ROLE_MANAGER') || $this->isGranted('ROLE_ADMIN'));
        if ($canClose) {
            $closeFormModel = $this->createForm(ReportCloseType::class);
            $closeFormModel->get('closureReason')->setData($detail->getClosureReason() ?? '');
            $closeForm = $closeFormModel->createView();
        } else {
            $closeForm = null;
        }

        return $this->render('report/show.html.twig', [
            'report' => $detail,
            'close_form' => $closeForm,
        ]);
    }

}
