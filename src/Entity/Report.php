<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $severity = null;

    #[ORM\Column(length: 255)]
    private ?string $situationType = null;

    #[ORM\ManyToOne(inversedBy: 'reports')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Driver $driver = null;

    #[ORM\ManyToOne(inversedBy: 'reports')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bus $Bus = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $reportDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $incidentDate = null;

    #[ORM\Column(type: Types::JSONB)]
    private mixed $reporterContact = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $aggravatingContext = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $mitigatingContext = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $situationSummary = null;

    #[ORM\Column(length: 32)]
    private ?string $reportCredibility = null;

    #[ORM\Column(type: Types::JSONB)]
    private mixed $metadata = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $closureReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getSituationType(): ?string
    {
        return $this->situationType;
    }

    public function setSituationType(string $situationType): static
    {
        $this->situationType = $situationType;

        return $this;
    }

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(?Driver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getBus(): ?Bus
    {
        return $this->Bus;
    }

    public function setBus(?Bus $Bus): static
    {
        $this->Bus = $Bus;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getReportDate(): ?\DateTimeImmutable
    {
        return $this->reportDate;
    }

    public function setReportDate(\DateTimeImmutable $reportDate): static
    {
        $this->reportDate = $reportDate;

        return $this;
    }

    public function getIncidentDate(): ?\DateTimeImmutable
    {
        return $this->incidentDate;
    }

    public function setIncidentDate(\DateTimeImmutable $incidentDate): static
    {
        $this->incidentDate = $incidentDate;

        return $this;
    }

    public function getReporterContact(): mixed
    {
        return $this->reporterContact;
    }

    public function setReporterContact(mixed $reporterContact): static
    {
        $this->reporterContact = $reporterContact;

        return $this;
    }

    public function getAggravatingContext(): ?string
    {
        return $this->aggravatingContext;
    }

    public function setAggravatingContext(string $aggravatingContext): static
    {
        $this->aggravatingContext = $aggravatingContext;

        return $this;
    }

    public function getMitigatingContext(): ?string
    {
        return $this->mitigatingContext;
    }

    public function setMitigatingContext(string $mitigatingContext): static
    {
        $this->mitigatingContext = $mitigatingContext;

        return $this;
    }

    public function getSituationSummary(): ?string
    {
        return $this->situationSummary;
    }

    public function setSituationSummary(string $situationSummary): static
    {
        $this->situationSummary = $situationSummary;

        return $this;
    }

    public function getReportCredibility(): ?string
    {
        return $this->reportCredibility;
    }

    public function setReportCredibility(string $reportCredibility): static
    {
        $this->reportCredibility = $reportCredibility;

        return $this;
    }

    public function getMetadata(): mixed
    {
        return $this->metadata;
    }

    public function setMetadata(mixed $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getClosureReason(): ?string
    {
        return $this->closureReason;
    }

    public function setClosureReason(?string $closureReason): static
    {
        $this->closureReason = $closureReason;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function isClosed(): bool
    {
        return null !== $this->closedAt;
    }
}
