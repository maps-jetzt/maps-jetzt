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
     * Get all tiles needed to cover the canvas centered on a bounding box.
     *
     * @return array{tiles: array<array{x: int, y: int}>, minTile: array{x: int, y: int}, maxTile: array{x: int, y: int}}
     */
    public function getTilesForCanvas(BoundingBox $bbox, int $zoom, int $canvasWidth, int $canvasHeight): array
    {
        // Get center of bounding box in pixels
        $center = $bbox->getCenter();
        $centerPixel = $this->latLonToPixel($center[0], $center[1], $zoom);

        // Calculate pixel bounds of the canvas
        $minPixelX = $centerPixel['x'] - $canvasWidth / 2;
        $maxPixelX = $centerPixel['x'] + $canvasWidth / 2;
        $minPixelY = $centerPixel['y'] - $canvasHeight / 2;
        $maxPixelY = $centerPixel['y'] + $canvasHeight / 2;

        // Convert pixel bounds to tile coordinates
        $minTileX = (int) floor($minPixelX / self::TILE_SIZE);
        $maxTileX = (int) floor($maxPixelX / self::TILE_SIZE);
        $minTileY = (int) floor($minPixelY / self::TILE_SIZE);
        $maxTileY = (int) floor($maxPixelY / self::TILE_SIZE);

        $tiles = [];
        for ($x = $minTileX; $x <= $maxTileX; $x++) {
            for ($y = $minTileY; $y <= $maxTileY; $y++) {
                $tiles[] = ['x' => $x, 'y' => $y];
            }
        }

        return [
            'tiles' => $tiles,
            'minTile' => ['x' => $minTileX, 'y' => $minTileY],
            'maxTile' => ['x' => $maxTileX, 'y' => $maxTileY],
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
