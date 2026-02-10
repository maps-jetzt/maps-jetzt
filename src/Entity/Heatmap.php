<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table()]
#[ORM\HasLifecycleCallbacks]
class Heatmap
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private int $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $identifier;

    #[ORM\Column(nullable: true)]
    private ?float $centerLon = null;

    #[ORM\Column(nullable: true)]
    private ?float $centerLat = null;

    #[ORM\Column(nullable: true)]
    private ?int $zoom = null;

    #[ORM\Column()]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'heatmaps')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    /** @var Collection<int, HeatmapPolyline> */
    #[ORM\OneToMany(targetEntity: HeatmapPolyline::class, mappedBy: 'heatmap', cascade: ['remove'])]
    private Collection $polylines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->polylines = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getCenterLon(): ?float
    {
        return $this->centerLon;
    }

    public function setCenterLon(?float $centerLon): self
    {
        $this->centerLon = $centerLon;
        return $this;
    }

    public function getCenterLat(): ?float
    {
        return $this->centerLat;
    }

    public function setCenterLat(?float $centerLat): self
    {
        $this->centerLat = $centerLat;
        return $this;
    }

    public function getZoom(): ?int
    {
        return $this->zoom;
    }

    public function setZoom(?int $zoom): self
    {
        $this->zoom = $zoom;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /** @return Collection<int, HeatmapPolyline> */
    public function getPolylines(): Collection
    {
        return $this->polylines;
    }
}
