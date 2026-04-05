<?php

namespace App\Entity;

use App\Repository\TransitDirectionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransitDirectionRepository::class)]
#[ORM\Table(name: 'transit_direction')]
#[ORM\UniqueConstraint(name: 'transit_direction_line_code', columns: ['line_id', 'code'])]
class TransitDirection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\ManyToOne(inversedBy: 'directions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TransitLine $line = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLine(): ?TransitLine
    {
        return $this->line;
    }

    public function setLine(?TransitLine $line): static
    {
        $this->line = $line;

        return $this;
    }
}
