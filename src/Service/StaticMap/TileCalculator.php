<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

/**
 * Handles tile and pixel coordinate calculations for web mercator projection.
 */
class TileCalculator
{
    private const int TILE_SIZE = 256;
    private const int MIN_ZOOM = 0;
    private const int MAX_ZOOM = 19;

    /**
     * Convert latitude/longitude to tile coordinates at a given zoom level.
     *
     * @return array{x: int, y: int}
     */
    public function latLonToTile(float $lat, float $lon, int $zoom): array
    {
        $n = 2 ** $zoom;
        $x = (int) floor(($lon + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n);

        return ['x' => $x, 'y' => $y];
    }

    /**
     * Convert latitude/longitude to pixel coordinates at a given zoom level.
     *
     * @return array{x: float, y: float}
     */
    public function latLonToPixel(float $lat, float $lon, int $zoom): array
    {
        $n = 2 ** $zoom;
        $x = ($lon + 180) / 360 * $n * self::TILE_SIZE;
        $y = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n * self::TILE_SIZE;

        return ['x' => $x, 'y' => $y];
    }

    /**
     * Calculate the optimal zoom level to fit a bounding box within given dimensions.
     */
    public function calculateOptimalZoom(BoundingBox $bbox, int $width, int $height): int
    {
        for ($zoom = self::MAX_ZOOM; $zoom >= self::MIN_ZOOM; $zoom--) {
            $minPixel = $this->latLonToPixel($bbox->maxLat, $bbox->minLon, $zoom);
            $maxPixel = $this->latLonToPixel($bbox->minLat, $bbox->maxLon, $zoom);

            $pixelWidth = abs($maxPixel['x'] - $minPixel['x']);
            $pixelHeight = abs($maxPixel['y'] - $minPixel['y']);

            if ($pixelWidth <= $width && $pixelHeight <= $height) {
                return $zoom;
            }
        }

        return self::MIN_ZOOM;
    }

    /**
     * Get all tiles needed to cover a bounding box at a given zoom level.
     *
     * @return array{tiles: array<array{x: int, y: int}>, minTile: array{x: int, y: int}, maxTile: array{x: int, y: int}}
     */
    public function getTilesForBounds(BoundingBox $bbox, int $zoom): array
    {
        $minTile = $this->latLonToTile($bbox->maxLat, $bbox->minLon, $zoom);
        $maxTile = $this->latLonToTile($bbox->minLat, $bbox->maxLon, $zoom);

        $tiles = [];
        for ($x = $minTile['x']; $x <= $maxTile['x']; $x++) {
            for ($y = $minTile['y']; $y <= $maxTile['y']; $y++) {
                $tiles[] = ['x' => $x, 'y' => $y];
            }
        }

        return [
            'tiles' => $tiles,
            'minTile' => $minTile,
            'maxTile' => $maxTile,
        ];
    }

    /**
     * Get the tile size in pixels.
     */
    public function getTileSize(): int
    {
        return self::TILE_SIZE;
    }
}
