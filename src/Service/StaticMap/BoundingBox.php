<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use App\DTO\StaticMap\MapElementInterface;

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

    /**
     * Create a bounding box from an array of map elements.
     *
     * @param array<int, MapElementInterface> $elements Array of map elements
     * @param float $singlePointPadding Padding in degrees for single point (default ~1km)
     */
    public static function fromElements(array $elements, float $singlePointPadding = 0.01): self
    {
        if (count($elements) === 0) {
            throw new \InvalidArgumentException('At least 1 element is required');
        }

        $allCoordinates = [];
        foreach ($elements as $element) {
            $allCoordinates = array_merge($allCoordinates, $element->getCoordinates());
        }

        if (count($allCoordinates) === 0) {
            throw new \InvalidArgumentException('Elements contain no coordinates');
        }

        $lats = array_column($allCoordinates, 0);
        $lons = array_column($allCoordinates, 1);

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLon = min($lons);
        $maxLon = max($lons);

        // Special case: single point (all coords are the same)
        if ($minLat === $maxLat && $minLon === $maxLon) {
            return new self(
                minLat: $minLat - $singlePointPadding,
                maxLat: $maxLat + $singlePointPadding,
                minLon: $minLon - $singlePointPadding,
                maxLon: $maxLon + $singlePointPadding,
            );
        }

        return new self(
            minLat: $minLat,
            maxLat: $maxLat,
            minLon: $minLon,
            maxLon: $maxLon,
        );
    }
}
