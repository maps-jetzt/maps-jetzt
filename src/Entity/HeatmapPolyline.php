<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Add index:
 * CREATE INDEX heatmap_polyline_geom_idx ON heatmap_polyline USING GIST (geom);
 */
#[ORM\Entity]
#[ORM\Table()]
class HeatmapPolyline
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Heatmap::class, inversedBy: 'polylines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Heatmap $heatmap;

    #[ORM\Column(type: "geometry", options: ["geometry_type" => "LINESTRING", "srid" => 3857])]
    private $geom;

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

    public function getGeom()
    {
        return $this->geom;
    }

    public function setGeom($geom): self
    {
        $this->geom = $geom;
        return $this;
    }
}
