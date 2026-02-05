<?php

declare(strict_types=1);

namespace App\DTO\StaticMap;

/**
 * Represents a marker element on a static map.
 */
readonly class MarkerElement implements MapElementInterface
{
    public function __construct(
        public float $lat,
        public float $lon,
        public string $icon = 'location-dot',
        public string $iconStyle = 'solid',
        public string $color = '#FF0000',
        public ?string $iconColor = null,
        public int $size = 32,
    ) {
    }

    public function getIconColor(): string
    {
        return $this->iconColor ?? '#FFFFFF';
    }

    public function getType(): string
    {
        return 'marker';
    }

    public function getCoordinates(): array
    {
        return [[$this->lat, $this->lon]];
    }
}
