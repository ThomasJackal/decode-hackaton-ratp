<?php

namespace App\Controller\Api;

use App\Entity\Report;
use App\Repository\BusRepository;
use App\Repository\DriverRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ApiReportController extends AbstractController
{
    /**
     * Correspond au modèle JSON métier (snake_case accepté en entrée, camelCase aussi).
     */
    #[Route('/reports', name: 'api_reports_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DriverRepository $driverRepository,
        BusRepository $busRepository,
    ): JsonResponse {
        if (!str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            return $this->json(['error' => 'Content-Type must be application/json'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $description = isset($data['description']) ? trim((string) $data['description']) : '';
        $severity = isset($data['severity']) ? trim((string) $data['severity']) : '';
        $situationType = '';
        if (isset($data['situation_type'])) {
            $situationType = trim((string) $data['situation_type']);
        } elseif (isset($data['situationType'])) {
            $situationType = trim((string) $data['situationType']);
        } elseif (isset($data['category'])) {
            $situationType = trim((string) $data['category']);
        }

        $driverId = $data['driverId'] ?? $data['driver_id'] ?? null;
        $busId = $data['busId'] ?? $data['bus_id'] ?? null;

        if ($description === '' || $severity === '' || $situationType === '') {
            return $this->json(
                ['error' => 'Fields description, severity, and situation_type (or category) are required and must be non-empty'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_numeric($driverId) || !is_numeric($busId)) {
            return $this->json(
                ['error' => 'Fields driverId and busId are required and must be numeric'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $driver = $driverRepository->find((int) $driverId);
        $bus = $busRepository->find((int) $busId);

        if (null === $driver || null === $bus) {
            return $this->json(['error' => 'Driver or bus not found'], Response::HTTP_BAD_REQUEST);
        }

        $reportDateRaw = $data['reportDate'] ?? $data['report_date'] ?? null;
        if (!is_string($reportDateRaw) || trim($reportDateRaw) === '') {
            return $this->json(['error' => 'report_date is required (ISO 8601 string)'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reportDate = new \DateTimeImmutable($reportDateRaw);
        } catch (\Exception) {
            return $this->json(['error' => 'report_date must be a valid date/time string'], Response::HTTP_BAD_REQUEST);
        }

        $incidentDate = $reportDate;
        $incidentRaw = $data['incidentDate'] ?? $data['incident_date'] ?? null;
        if (is_string($incidentRaw) && trim($incidentRaw) !== '') {
            try {
                $incidentDate = new \DateTimeImmutable($incidentRaw);
            } catch (\Exception) {
                return $this->json(['error' => 'incident_date must be a valid date/time string when provided'], Response::HTTP_BAD_REQUEST);
            }
        }

        $reporterContact = $data['reporterContact'] ?? $data['reporter_contact'] ?? [];
        if (!is_array($reporterContact)) {
            return $this->json(['error' => 'reporter_contact must be a JSON object (associative array)'], Response::HTTP_BAD_REQUEST);
        }

        $createdAtRaw = $data['createdAt'] ?? $data['created_at'] ?? null;
        $createdAt = new \DateTimeImmutable();
        if (is_string($createdAtRaw) && trim($createdAtRaw) !== '') {
            try {
                $createdAt = new \DateTimeImmutable($createdAtRaw);
            } catch (\Exception) {
                return $this->json(['error' => 'created_at must be a valid date/time string when provided'], Response::HTTP_BAD_REQUEST);
            }
        }

        $aggravating = $data['aggravating_context'] ?? $data['aggravatingContext'] ?? $data['aggraving_context'] ?? '';
        $mitigating = $data['mitigating_context'] ?? $data['mitigatingContext'] ?? '';
        $credibility = $data['report_credibility'] ?? $data['reportCredibility'] ?? '';
        $summary = $data['situation_summary'] ?? $data['situationSummary'] ?? '';

        $report = (new Report())
            ->setDescription($description)
            ->setSeverity($severity)
            ->setSituationType($situationType)
            ->setDriver($driver)
            ->setBus($bus)
            ->setReportDate($reportDate)
            ->setIncidentDate($incidentDate)
            ->setReporterContact($reporterContact)
            ->setAggravatingContext(is_string($aggravating) ? $aggravating : '')
            ->setMitigatingContext(is_string($mitigating) ? $mitigating : '')
            ->setReportCredibility(is_string($credibility) ? $credibility : '')
            ->setSituationSummary(is_string($summary) ? $summary : '')
            ->setCreatedAt($createdAt);

        $meta = $data['metadata'] ?? [];
        $report->setMetadata(is_array($meta) ? $meta : []);

        $em->persist($report);
        $em->flush();

        return $this->json(
            $this->serializeReportOutput($report),
            Response::HTTP_CREATED
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReportOutput(Report $report): array
    {
        $rc = $report->getReporterContact();

        return [
            'id' => $report->getId(),
            'driver_id' => $report->getDriver()?->getId(),
            'bus_id' => $report->getBus()?->getId(),
            'description' => $report->getDescription(),
            'report_date' => $report->getReportDate()?->format(\DateTimeInterface::ATOM),
            'incident_date' => $report->getIncidentDate()?->format(\DateTimeInterface::ATOM),
            'reporter_contact' => is_array($rc) ? $rc : [],
            'situation_type' => $report->getSituationType(),
            'aggravating_context' => $report->getAggravatingContext(),
            'mitigating_context' => $report->getMitigatingContext(),
            'report_credibility' => $report->getReportCredibility(),
            'situation_summary' => $report->getSituationSummary(),
            'severity' => $report->getSeverity(),
            'created_at' => $report->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'metadata' => $report->getMetadata(),
        ];
    }
}
