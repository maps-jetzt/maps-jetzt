<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table()]
class Heatmap
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private int $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $identifier;

    #[ORM\Column()]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, HeatmapPolyline> */
    #[ORM\OneToMany(targetEntity: HeatmapPolyline::class, mappedBy: 'heatmap', cascade: ['remove'])]
    private Collection $polylines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->polylines = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, HeatmapPolyline> */
    public function getPolylines(): Collection
    {
        return $this->polylines;
    }
}
