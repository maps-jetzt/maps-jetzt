<?php

declare(strict_types=1);

namespace App\DTO\StaticMap;

/**
 * Interface for map elements that can be rendered on a static map.
 */
interface MapElementInterface
{
    /**
     * Get the element type identifier.
     */
    public function getType(): string;

    /**
     * Get all coordinates associated with this element.
     *
     * @return array<int, array{0: float, 1: float}> Array of [lat, lon] coordinates
     */
    public function getCoordinates(): array;
}
