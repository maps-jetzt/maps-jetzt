<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

/**
 * Value object representing a geographic bounding box.
 */
readonly class BoundingBox
{
    public function __construct(
        public float $minLat,
        public float $maxLat,
        public float $minLon,
        public float $maxLon,
    ) {
    }

    /**
     * Create a bounding box from an array of coordinates.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates Array of [lat, lon] coordinates
     */
    public static function fromCoordinates(array $coordinates): self
    {
        if (count($coordinates) < 2) {
            throw new \InvalidArgumentException('At least 2 coordinates are required');
        }

        $lats = array_column($coordinates, 0);
        $lons = array_column($coordinates, 1);

        return new self(
            minLat: min($lats),
            maxLat: max($lats),
            minLon: min($lons),
            maxLon: max($lons),
        );
    }

    /**
     * Expand the bounding box by a factor (e.g., 0.1 for 10% padding).
     */
    public function expand(float $factor): self
    {
        $latDiff = ($this->maxLat - $this->minLat) * $factor;
        $lonDiff = ($this->maxLon - $this->minLon) * $factor;

        return new self(
            minLat: $this->minLat - $latDiff,
            maxLat: $this->maxLat + $latDiff,
            minLon: $this->minLon - $lonDiff,
            maxLon: $this->maxLon + $lonDiff,
        );
    }

    /**
     * Get the center point of the bounding box.
     *
     * @return array{0: float, 1: float} [lat, lon]
     */
    public function getCenter(): array
    {
        return [
            ($this->minLat + $this->maxLat) / 2,
            ($this->minLon + $this->maxLon) / 2,
        ];
    }

    /**
     * Get the width in degrees.
     */
    public function getWidth(): float
    {
        return $this->maxLon - $this->minLon;
    }

    /**
     * Get the height in degrees.
     */
    public function getHeight(): float
    {
        return $this->maxLat - $this->minLat;
    }
}
