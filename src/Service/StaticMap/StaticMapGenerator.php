<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use App\DTO\StaticMap\MapElementInterface;

/**
 * Orchestrates the generation of static map images with routes.
 */
class StaticMapGenerator
{
    public function __construct(
        private readonly PolylineDecoder $polylineDecoder,
        private readonly TileCalculator $tileCalculator,
        private readonly TileFetcher $tileFetcher,
        private readonly ImageComposer $imageComposer,
    ) {
    }

    /**
     * Generate a static map image with a route.
     */
    public function generate(
        string $polyline,
        int $width,
        int $height,
        string $color = '#FF0000',
        int $strokeWidth = 3,
    ): \GdImage {
        // 1. Decode polyline to coordinates
        $coordinates = $this->polylineDecoder->decode($polyline);

        if (count($coordinates) < 2) {
            throw new \InvalidArgumentException('Polyline must contain at least 2 points');
        }

        // 2. Calculate bounding box with 10% padding
        $bbox = BoundingBox::fromCoordinates($coordinates)->expand(0.1);

        // 3. Calculate optimal zoom level
        $zoom = $this->tileCalculator->calculateOptimalZoom($bbox, $width, $height);

        // 4. Get required tiles for entire canvas
        $tilesInfo = $this->tileCalculator->getTilesForCanvas($bbox, $zoom, $width, $height);

        // 5. Fetch tiles in parallel
        $tileImages = $this->tileFetcher->fetchTiles($tilesInfo['tiles'], $zoom);

        // 6. Compose tiles onto canvas
        $canvas = $this->imageComposer->composeTiles(
            $width,
            $height,
            $tileImages,
            $tilesInfo['minTile'],
            $bbox,
            $zoom,
        );

        // 7. Draw the route
        $this->imageComposer->drawRoute($canvas, $coordinates, $bbox, $zoom, $color, $strokeWidth);

        // 8. Add attribution
        $this->imageComposer->addAttribution($canvas);

        return $canvas;
    }

    /**
     * Generate a static map image with multiple elements.
     *
     * @param array<int, MapElementInterface> $elements
     */
    public function generateWithElements(array $elements, int $width, int $height): \GdImage
    {
        if (count($elements) === 0) {
            throw new \InvalidArgumentException('At least one element is required');
        }

        // 1. Calculate bounding box from all elements with 10% padding
        $bbox = BoundingBox::fromElements($elements)->expand(0.1);

        // 2. Calculate optimal zoom level
        $zoom = $this->tileCalculator->calculateOptimalZoom($bbox, $width, $height);

        // 3. Get required tiles for entire canvas
        $tilesInfo = $this->tileCalculator->getTilesForCanvas($bbox, $zoom, $width, $height);

        // 4. Fetch tiles in parallel
        $tileImages = $this->tileFetcher->fetchTiles($tilesInfo['tiles'], $zoom);

        // 5. Compose tiles onto canvas
        $canvas = $this->imageComposer->composeTiles(
            $width,
            $height,
            $tileImages,
            $tilesInfo['minTile'],
            $bbox,
            $zoom,
        );

        // 6. Draw all elements
        $this->imageComposer->drawElements($canvas, $elements, $bbox, $zoom);

        // 7. Add attribution
        $this->imageComposer->addAttribution($canvas);

        return $canvas;
    }
}
