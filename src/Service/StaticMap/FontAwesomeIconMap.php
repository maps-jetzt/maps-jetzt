<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

/**
 * Maps FontAwesome icon names to Unicode codepoints and font paths.
 */
class FontAwesomeIconMap
{
    /**
     * Map of icon names to Unicode codepoints.
     * These are the most common icons for map markers.
     *
     * @var array<string, string>
     */
    private const array ICONS = [
        // Location & Navigation
        'location-dot' => "\u{f3c5}",
        'location-pin' => "\u{f041}",
        'map-pin' => "\u{f276}",
        'map-marker' => "\u{f041}",
        'compass' => "\u{f14e}",
        'location-crosshairs' => "\u{f601}",

        // Points of Interest
        'flag' => "\u{f024}",
        'flag-checkered' => "\u{f11e}",
        'star' => "\u{f005}",
        'heart' => "\u{f004}",
        'circle' => "\u{f111}",
        'square' => "\u{f0c8}",

        // Buildings & Places
        'home' => "\u{f015}",
        'building' => "\u{f1ad}",
        'store' => "\u{f54e}",
        'hospital' => "\u{f0f8}",
        'school' => "\u{f549}",
        'church' => "\u{f51d}",
        'mosque' => "\u{f678}",
        'synagogue' => "\u{f69b}",

        // Transport
        'parking' => "\u{f540}",
        'gas-pump' => "\u{f52f}",
        'charging-station' => "\u{f5e7}",
        'car' => "\u{f1b9}",
        'bus' => "\u{f207}",
        'train' => "\u{f238}",
        'plane' => "\u{f072}",
        'ship' => "\u{f21a}",
        'bicycle' => "\u{f206}",

        // Activities
        'play' => "\u{f04b}",
        'stop' => "\u{f04d}",
        'pause' => "\u{f04c}",
        'check' => "\u{f00c}",
        'xmark' => "\u{f00d}",
        'plus' => "\u{f067}",
        'minus' => "\u{f068}",

        // Food & Drink
        'utensils' => "\u{f2e7}",
        'mug-hot' => "\u{f7b6}",
        'wine-glass' => "\u{f4e3}",
        'beer-mug-empty' => "\u{f0fc}",
        'pizza-slice' => "\u{f818}",
        'ice-cream' => "\u{f810}",

        // Accommodation
        'bed' => "\u{f236}",
        'hotel' => "\u{f594}",
        'campground' => "\u{f6bb}",
        'caravan' => "\u{f8ff}",

        // Nature & Outdoor
        'mountain' => "\u{f6fc}",
        'mountain-sun' => "\u{e52f}",
        'tree' => "\u{f1bb}",
        'water' => "\u{f773}",
        'umbrella-beach' => "\u{f5ca}",

        // Sports & Recreation
        'person-hiking' => "\u{f6ec}",
        'person-biking' => "\u{f84a}",
        'person-swimming' => "\u{f5c4}",
        'person-skiing' => "\u{f7c9}",
        'golf-ball-tee' => "\u{f450}",
        'futbol' => "\u{f1e3}",

        // Photography & Media
        'camera' => "\u{f030}",
        'camera-retro' => "\u{f083}",
        'image' => "\u{f03e}",
        'panorama' => "\u{e209}",

        // Information
        'info' => "\u{f129}",
        'circle-info' => "\u{f05a}",
        'question' => "\u{f128}",
        'circle-question' => "\u{f059}",
        'triangle-exclamation' => "\u{f071}",
        'circle-exclamation' => "\u{f06a}",

        // Numbers (FontAwesome icons)
        '1' => "\u{0031}",
        '2' => "\u{0032}",
        '3' => "\u{0033}",
        '4' => "\u{0034}",
        '5' => "\u{0035}",
        '6' => "\u{0036}",
        '7' => "\u{0037}",
        '8' => "\u{0038}",
        '9' => "\u{0039}",
        '0' => "\u{0030}",

        // Letters
        'a' => "\u{0041}",
        'b' => "\u{0042}",
        'c' => "\u{0043}",
        'd' => "\u{0044}",
        'e' => "\u{0045}",
        'f' => "\u{0046}",
    ];

    /**
     * Font file names for each style.
     *
     * @var array<string, string>
     */
    private const array FONT_FILES = [
        'solid' => 'fa-solid-900.ttf',
        'regular' => 'fa-regular-400.ttf',
        'brands' => 'fa-brands-400.ttf',
    ];

    public function __construct(
        private readonly string $fontsDir,
    ) {
    }

    /**
     * Get the Unicode character for an icon name.
     *
     * @throws \InvalidArgumentException If icon is not found
     */
    public function getIconChar(string $iconName): string
    {
        if (!isset(self::ICONS[$iconName])) {
            throw new \InvalidArgumentException(sprintf('Unknown icon: %s', $iconName));
        }

        return self::ICONS[$iconName];
    }

    /**
     * Check if an icon exists.
     */
    public function hasIcon(string $iconName): bool
    {
        return isset(self::ICONS[$iconName]);
    }

    /**
     * Get the font path for a style.
     *
     * @throws \InvalidArgumentException If style is not valid
     */
    public function getFontPath(string $style = 'solid'): string
    {
        if (!isset(self::FONT_FILES[$style])) {
            throw new \InvalidArgumentException(sprintf('Unknown font style: %s', $style));
        }

        $path = $this->fontsDir . '/' . self::FONT_FILES[$style];

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Font file not found: %s', $path));
        }

        return $path;
    }

    /**
     * Get all available icon names.
     *
     * @return array<string>
     */
    public function getAvailableIcons(): array
    {
        return array_keys(self::ICONS);
    }
}
