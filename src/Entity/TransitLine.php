<?php

namespace App\Entity;

use App\Repository\TransitLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransitLineRepository::class)]
#[ORM\Table(name: 'transit_line')]
#[ORM\UniqueConstraint(name: 'transit_line_code_unique', columns: ['code'])]
class TransitLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, TransitDirection>
     */
    #[ORM\OneToMany(targetEntity: TransitDirection::class, mappedBy: 'line', orphanRemoval: true)]
    private Collection $directions;

    public function __construct()
    {
        $this->directions = new ArrayCollection();
    }

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, TransitDirection>
     */
    public function getDirections(): Collection
    {
        return $this->directions;
    }

    public function addDirection(TransitDirection $direction): static
    {
        if (!$this->directions->contains($direction)) {
            $this->directions->add($direction);
            $direction->setLine($this);
        }

        return $this;
    }

    public function removeDirection(TransitDirection $direction): static
    {
        $this->directions->removeElement($direction);

        return $this;
    }
}
