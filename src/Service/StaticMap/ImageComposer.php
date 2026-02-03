<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

/**
 * Composes tiles and draws routes onto a canvas using GD.
 */
class ImageComposer
{
    private const string ATTRIBUTION = '© OpenStreetMap contributors';
    private const int ATTRIBUTION_PADDING = 5;

    public function __construct(
        private readonly TileCalculator $tileCalculator,
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
     * Add OSM attribution to the canvas.
     */
    public function addAttribution(\GdImage $canvas): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);

        // Create semi-transparent background for attribution
        $bgColor = imagecolorallocatealpha($canvas, 255, 255, 255, 50);
        $textColor = imagecolorallocate($canvas, 0, 0, 0);

        $fontSize = 2; // GD built-in font size (1-5)
        $fontWidth = imagefontwidth($fontSize);
        $fontHeight = imagefontheight($fontSize);
        $textWidth = strlen(self::ATTRIBUTION) * $fontWidth;

        $x = $width - $textWidth - self::ATTRIBUTION_PADDING;
        $y = $height - $fontHeight - self::ATTRIBUTION_PADDING;

        // Draw background rectangle
        imagefilledrectangle(
            $canvas,
            $x - self::ATTRIBUTION_PADDING,
            $y - self::ATTRIBUTION_PADDING,
            $width,
            $height,
            $bgColor,
        );

        // Draw text
        imagestring($canvas, $fontSize, $x, $y, self::ATTRIBUTION, $textColor);
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
