<?php

namespace App\Entity;

use App\Repository\BusServingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BusServingRepository::class)]
#[ORM\Table(name: 'bus_serving')]
#[ORM\UniqueConstraint(name: 'bus_serving_line_stop_dir_unique', columns: ['line_id', 'stop_id', 'direction_id'])]
class BusServing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bus $bus = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TransitLine $line = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TransitStop $stop = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TransitDirection $direction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static
    {
        $this->bus = $bus;

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

    public function getStop(): ?TransitStop
    {
        return $this->stop;
    }

    public function setStop(?TransitStop $stop): static
    {
        $this->stop = $stop;

        return $this;
    }

    public function getDirection(): ?TransitDirection
    {
        return $this->direction;
    }

    public function setDirection(?TransitDirection $direction): static
    {
        $this->direction = $direction;

        return $this;
    }
}
