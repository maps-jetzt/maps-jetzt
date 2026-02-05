<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use App\DTO\StaticMap\MarkerElement;

/**
 * Renders map markers with FontAwesome icons using GD.
 */
class MarkerRenderer
{
    private const int SUPERSAMPLE_FACTOR = 4;

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
        $borderWidth = max(2, (int) ($size * 0.08));
        $totalSize = $size + $borderWidth * 2 + 4; // Extra padding

        // Create high-resolution sprite for supersampling
        $scale = self::SUPERSAMPLE_FACTOR;
        $spriteSize = $totalSize * $scale;
        $sprite = imagecreatetruecolor($spriteSize, $spriteSize);

        // Enable alpha blending and save alpha
        imagesavealpha($sprite, true);
        imagealphablending($sprite, false);

        // Fill with transparent background
        $transparent = imagecolorallocatealpha($sprite, 0, 0, 0, 127);
        imagefilledrectangle($sprite, 0, 0, $spriteSize - 1, $spriteSize - 1, $transparent);

        imagealphablending($sprite, true);

        // Calculate center of sprite
        $cx = (int) ($spriteSize / 2);
        $cy = (int) ($spriteSize / 2);

        // Allocate colors
        $rgb = $this->parseHexColor($marker->color);
        $circleColor = imagecolorallocate($sprite, $rgb['r'], $rgb['g'], $rgb['b']);
        $borderColor = imagecolorallocate($sprite, 255, 255, 255);

        // Draw at scaled size
        $scaledRadius = $radius * $scale;
        $scaledBorderWidth = $borderWidth * $scale;

        // Draw white border/outline (slightly larger circle)
        imagefilledellipse($sprite, $cx, $cy, ($scaledRadius + $scaledBorderWidth) * 2, ($scaledRadius + $scaledBorderWidth) * 2, $borderColor);

        // Draw the colored circle
        imagefilledellipse($sprite, $cx, $cy, $scaledRadius * 2, $scaledRadius * 2, $circleColor);

        // Draw the icon at scaled size
        $this->drawIcon($sprite, $marker, $cx, $cy, $scaledRadius);

        // Resample down to target size (this creates the antialiasing effect)
        $finalSprite = imagecreatetruecolor($totalSize, $totalSize);
        imagesavealpha($finalSprite, true);
        imagealphablending($finalSprite, false);
        $transparent2 = imagecolorallocatealpha($finalSprite, 0, 0, 0, 127);
        imagefilledrectangle($finalSprite, 0, 0, $totalSize - 1, $totalSize - 1, $transparent2);
        imagealphablending($finalSprite, true);

        imagecopyresampled(
            $finalSprite,
            $sprite,
            0, 0,
            0, 0,
            $totalSize, $totalSize,
            $spriteSize, $spriteSize
        );

        // Copy to canvas with alpha blending
        imagealphablending($canvas, true);
        $destX = $x - (int) ($totalSize / 2);
        $destY = $y - (int) ($totalSize / 2);

        imagecopy($canvas, $finalSprite, $destX, $destY, 0, 0, $totalSize, $totalSize);

        // Clean up
        imagedestroy($sprite);
        imagedestroy($finalSprite);
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

        // Font size roughly 50% of circle diameter
        $fontSize = $radius * 0.95;

        // Get bounding box at this font size
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $iconChar);
        if ($bbox === false) {
            return;
        }

        // Calculate width and height
        $width = $bbox[2] - $bbox[0];
        $height = $bbox[1] - $bbox[7];

        // Calculate position for centering
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
