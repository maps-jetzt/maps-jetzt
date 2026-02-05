<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use App\DTO\StaticMap\MapElementInterface;
use App\DTO\StaticMap\MarkerElement;
use App\DTO\StaticMap\PolylineElement;

/**
 * Composes tiles and draws routes onto a canvas using GD.
 */
class ImageComposer
{
    private const string ATTRIBUTION = '© OpenStreetMap contributors';
    private const int ATTRIBUTION_PADDING = 6;
    private const float FONT_SIZE = 10.0;

    public function __construct(
        private readonly TileCalculator $tileCalculator,
        private readonly ?string $fontPath = null,
        private readonly ?MarkerRenderer $markerRenderer = null,
    ) {
    }

    /**
     * Create a canvas and compose tiles onto it.
     *
     * @param array<string, \GdImage> $tiles Map of "x,y" => GdImage
     * @param array{x: int, y: int} $minTile
     */
    public function composeTiles(
        int $width,
        int $height,
        array $tiles,
        array $minTile,
        BoundingBox $bbox,
        int $zoom,
    ): \GdImage {
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $tileSize = $this->tileCalculator->getTileSize();

        // Calculate the pixel offset for centering
        $bboxCenter = $bbox->getCenter();
        $centerPixel = $this->tileCalculator->latLonToPixel($bboxCenter[0], $bboxCenter[1], $zoom);

        // The center of our canvas should correspond to the center of the bbox
        $offsetX = $centerPixel['x'] - $width / 2;
        $offsetY = $centerPixel['y'] - $height / 2;

        // Place each tile
        foreach ($tiles as $key => $tileImage) {
            [$tileX, $tileY] = explode(',', $key);
            $tileX = (int) $tileX;
            $tileY = (int) $tileY;

            $destX = (int) ($tileX * $tileSize - $offsetX);
            $destY = (int) ($tileY * $tileSize - $offsetY);

            imagecopy($canvas, $tileImage, $destX, $destY, 0, 0, $tileSize, $tileSize);
            imagedestroy($tileImage);
        }

        return $canvas;
    }

    /**
     * Draw a polyline route on the canvas.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates
     */
    public function drawRoute(
        \GdImage $canvas,
        array $coordinates,
        BoundingBox $bbox,
        int $zoom,
        string $color,
        int $strokeWidth,
    ): void {
        $width = imagesx($canvas);
        $height = imagesy($canvas);

        // Calculate offset (same as in composeTiles)
        $bboxCenter = $bbox->getCenter();
        $centerPixel = $this->tileCalculator->latLonToPixel($bboxCenter[0], $bboxCenter[1], $zoom);
        $offsetX = $centerPixel['x'] - $width / 2;
        $offsetY = $centerPixel['y'] - $height / 2;

        // Parse color
        $rgb = $this->parseHexColor($color);
        $lineColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);

        // Set line thickness
        imagesetthickness($canvas, $strokeWidth);

        // Enable anti-aliasing
        imageantialias($canvas, true);

        // Draw the polyline
        $prevPixel = null;
        foreach ($coordinates as $coord) {
            $pixel = $this->tileCalculator->latLonToPixel($coord[0], $coord[1], $zoom);
            $x = (int) ($pixel['x'] - $offsetX);
            $y = (int) ($pixel['y'] - $offsetY);

            if ($prevPixel !== null) {
                imageline($canvas, $prevPixel['x'], $prevPixel['y'], $x, $y, $lineColor);
            }

            $prevPixel = ['x' => $x, 'y' => $y];
        }
    }

    /**
     * Draw all map elements on the canvas.
     * Elements are drawn in order: polylines first, then markers (to ensure markers are on top).
     *
     * @param array<int, MapElementInterface> $elements
     */
    public function drawElements(
        \GdImage $canvas,
        array $elements,
        BoundingBox $bbox,
        int $zoom,
    ): void {
        // Separate elements by type for proper z-ordering
        $polylines = [];
        $markers = [];

        foreach ($elements as $element) {
            if ($element instanceof PolylineElement) {
                $polylines[] = $element;
            } elseif ($element instanceof MarkerElement) {
                $markers[] = $element;
            }
        }

        // Draw polylines first
        foreach ($polylines as $polyline) {
            $this->drawRoute($canvas, $polyline->coordinates, $bbox, $zoom, $polyline->color, $polyline->strokeWidth);
        }

        // Draw markers on top
        foreach ($markers as $marker) {
            $this->drawMarker($canvas, $marker, $bbox, $zoom);
        }
    }

    /**
     * Draw a single marker on the canvas.
     */
    public function drawMarker(
        \GdImage $canvas,
        MarkerElement $marker,
        BoundingBox $bbox,
        int $zoom,
    ): void {
        if ($this->markerRenderer === null) {
            return;
        }

        $width = imagesx($canvas);
        $height = imagesy($canvas);

        // Calculate offset (same as in composeTiles)
        $bboxCenter = $bbox->getCenter();
        $centerPixel = $this->tileCalculator->latLonToPixel($bboxCenter[0], $bboxCenter[1], $zoom);
        $offsetX = $centerPixel['x'] - $width / 2;
        $offsetY = $centerPixel['y'] - $height / 2;

        // Convert lat/lon to pixel coordinates
        $pixel = $this->tileCalculator->latLonToPixel($marker->lat, $marker->lon, $zoom);
        $x = (int) ($pixel['x'] - $offsetX);
        $y = (int) ($pixel['y'] - $offsetY);

        // Draw the marker (y is the tip of the marker)
        $this->markerRenderer->drawMarker($canvas, $marker, $x, $y);
    }

    /**
     * Add OSM attribution to the canvas.
     */
    public function addAttribution(\GdImage $canvas): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);

        // Create semi-transparent background for attribution
        $bgColor = imagecolorallocatealpha($canvas, 255, 255, 255, 50);
        $textColor = imagecolorallocate($canvas, 0, 0, 0);

        // Try to use TrueType font, fallback to built-in font
        $fontPath = $this->findFontPath();

        if ($fontPath !== null) {
            $this->drawAttributionTtf($canvas, $width, $height, $fontPath, $bgColor, $textColor);
        } else {
            $this->drawAttributionBuiltin($canvas, $width, $height, $bgColor, $textColor);
        }
    }

    private function drawAttributionTtf(
        \GdImage $canvas,
        int $width,
        int $height,
        string $fontPath,
        int $bgColor,
        int $textColor,
    ): void {
        $bbox = imagettfbbox(self::FONT_SIZE, 0, $fontPath, self::ATTRIBUTION);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];

        $x = $width - $textWidth - self::ATTRIBUTION_PADDING;
        $y = $height - self::ATTRIBUTION_PADDING;

        // Draw background rectangle
        imagefilledrectangle(
            $canvas,
            $x - self::ATTRIBUTION_PADDING,
            $y - $textHeight - self::ATTRIBUTION_PADDING,
            $width,
            $height,
            $bgColor,
        );

        // Draw text with TrueType font
        imagettftext($canvas, self::FONT_SIZE, 0, (int) $x, (int) $y, $textColor, $fontPath, self::ATTRIBUTION);
    }

    private function drawAttributionBuiltin(
        \GdImage $canvas,
        int $width,
        int $height,
        int $bgColor,
        int $textColor,
    ): void {
        $fontSize = 2;
        $fontWidth = imagefontwidth($fontSize);
        $fontHeight = imagefontheight($fontSize);
        $textWidth = strlen(self::ATTRIBUTION) * $fontWidth;

        $x = $width - $textWidth - self::ATTRIBUTION_PADDING;
        $y = $height - $fontHeight - self::ATTRIBUTION_PADDING;

        imagefilledrectangle(
            $canvas,
            $x - self::ATTRIBUTION_PADDING,
            $y - self::ATTRIBUTION_PADDING,
            $width,
            $height,
            $bgColor,
        );

        imagestring($canvas, $fontSize, $x, $y, self::ATTRIBUTION, $textColor);
    }

    private function findFontPath(): ?string
    {
        // Use configured font path if available
        if ($this->fontPath !== null && is_file($this->fontPath)) {
            return $this->fontPath;
        }

        // Try common font locations
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',        // macOS
            '/System/Library/Fonts/Helvetica.ttc',                  // macOS
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',     // Linux
            '/usr/share/fonts/TTF/DejaVuSans.ttf',                 // Arch Linux
            'C:/Windows/Fonts/arial.ttf',                           // Windows
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Parse a hex color string to RGB values.
     *
     * @return array{r: int, g: int, b: int}
     */
    private function parseHexColor(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
