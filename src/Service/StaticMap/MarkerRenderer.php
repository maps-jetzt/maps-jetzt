<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use App\DTO\StaticMap\MarkerElement;

/**
 * Renders map markers with FontAwesome icons using GD.
 */
class MarkerRenderer
{
    public function __construct(
        private readonly FontAwesomeIconMap $iconMap,
    ) {
    }

    /**
     * Draw a marker on the canvas at the specified pixel position.
     *
     * @param int $x X pixel position (center of marker)
     * @param int $y Y pixel position (center of marker)
     */
    public function drawMarker(\GdImage $canvas, MarkerElement $marker, int $x, int $y): void
    {
        $size = $marker->size;
        $radius = (int) ($size / 2);

        $rgb = $this->parseHexColor($marker->color);

        // Enable antialiasing
        imageantialias($canvas, true);

        // Allocate colors
        $circleColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
        $borderColor = imagecolorallocate($canvas, 255, 255, 255);

        // Draw white border/outline (slightly larger circle)
        $borderWidth = max(2, (int) ($size * 0.08));
        imagefilledellipse($canvas, $x, $y, ($radius + $borderWidth) * 2, ($radius + $borderWidth) * 2, $borderColor);

        // Draw the colored circle
        imagefilledellipse($canvas, $x, $y, $radius * 2, $radius * 2, $circleColor);

        // Draw the icon
        $this->drawIcon($canvas, $marker, $x, $y, $radius);
    }

    /**
     * Draw the FontAwesome icon inside the marker.
     */
    private function drawIcon(\GdImage $canvas, MarkerElement $marker, int $x, int $y, int $radius): void
    {
        try {
            $iconChar = $this->iconMap->getIconChar($marker->icon);
            $fontPath = $this->iconMap->getFontPath($marker->iconStyle);
        } catch (\InvalidArgumentException|\RuntimeException) {
            return;
        }

        $rgb = $this->parseHexColor($marker->getIconColor());
        $iconColor = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);

        // Font size roughly 55% of circle diameter
        $fontSize = $radius * 1.1;

        // Get bounding box at this font size
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $iconChar);
        if ($bbox === false) {
            return;
        }

        // imagettfbbox returns:
        // 0,1 = lower left corner
        // 2,3 = lower right corner
        // 4,5 = upper right corner
        // 6,7 = upper left corner

        // Calculate width and height
        $width = $bbox[2] - $bbox[0];
        $height = $bbox[1] - $bbox[7];

        // Calculate top-left position for centering
        // imagettftext Y coordinate is the baseline
        $textX = $x - ($width / 2) - $bbox[0];
        $textY = $y - ($height / 2) - $bbox[7];

        imagettftext($canvas, $fontSize, 0, (int) round($textX), (int) round($textY), $iconColor, $fontPath, $iconChar);
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
