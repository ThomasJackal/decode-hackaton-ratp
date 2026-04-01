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
     * {
     *  "description": "Broken headlight",
     *  "severity": "medium",
     *  "category": "safety",
     *  "driverId": 1,
     *  "busId": 1,
     *  "reportDate": "2026-03-31T10:00:00+00:00",
     *  "reporterContact": { "name": "Jane", "email": "j@example.com" }
     * }
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
        $category = isset($data['category']) ? trim((string) $data['category']) : '';

        $driverId = $data['driverId'] ?? $data['driver_id'] ?? null;
        $busId = $data['busId'] ?? $data['bus_id'] ?? null;

        if ($description === '' || $severity === '' || $category === '') {
            return $this->json(
                ['error' => 'Fields description, severity, and category are required and must be non-empty'],
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
            return $this->json(['error' => 'reportDate is required (ISO 8601 string)'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reportDate = new \DateTimeImmutable($reportDateRaw);
        } catch (\Exception) {
            return $this->json(['error' => 'reportDate must be a valid date/time string'], Response::HTTP_BAD_REQUEST);
        }

        $reporterContact = $data['reporterContact'] ?? $data['reporter_contact'] ?? [];
        if (!is_array($reporterContact)) {
            return $this->json(['error' => 'reporterContact must be a JSON object (associative array)'], Response::HTTP_BAD_REQUEST);
        }

        $createdAtRaw = $data['createdAt'] ?? $data['created_at'] ?? null;
        $createdAt = new \DateTimeImmutable();
        if (is_string($createdAtRaw) && trim($createdAtRaw) !== '') {
            try {
                $createdAt = new \DateTimeImmutable($createdAtRaw);
            } catch (\Exception) {
                return $this->json(['error' => 'createdAt must be a valid date/time string when provided'], Response::HTTP_BAD_REQUEST);
            }
        }

        $report = (new Report())
            ->setDescription($description)
            ->setSeverity($severity)
            ->setCategory($category)
            ->setDriver($driver)
            ->setBus($bus)
            ->setReportDate($reportDate)
            ->setReporterContact($reporterContact)
            ->setCreatedAt($createdAt);

        $em->persist($report);
        $em->flush();

        return $this->json(
            [
                'id' => $report->getId(),
                'description' => $report->getDescription(),
                'severity' => $report->getSeverity(),
                'category' => $report->getCategory(),
                'driverId' => $report->getDriver()?->getId(),
                'busId' => $report->getBus()?->getId(),
                'createdAt' => $report->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'reportDate' => $report->getReportDate()?->format(\DateTimeInterface::ATOM),
                'reporterContact' => $report->getReporterContact(),
            ],
            Response::HTTP_CREATED
        );
    }
}
