<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Add index:
 * CREATE INDEX heatmap_polyline_geom_idx ON heatmap_polyline USING GIST (geom);
 */
#[ORM\Entity]
#[ORM\Table()]
#[ORM\UniqueConstraint(columns: ['heatmap_id', 'hash'])]
#[ORM\HasLifecycleCallbacks]
class HeatmapPolyline
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Heatmap::class, inversedBy: 'polylines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Heatmap $heatmap;

    #[ORM\Column(length: 32)]
    private string $hash;

    #[ORM\Column(type: "geometry", options: ["geometry_type" => "LINESTRING", "srid" => 3857])]
    private $geom;

    #[ORM\Column()]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHeatmap(): Heatmap
    {
        return $this->heatmap;
    }

    public function setHeatmap(Heatmap $heatmap): self
    {
        $this->heatmap = $heatmap;
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }

    public function getGeom()
    {
        return $this->geom;
    }

    public function setGeom($geom): self
    {
        $this->geom = $geom;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
