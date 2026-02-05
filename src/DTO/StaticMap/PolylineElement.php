<?php

declare(strict_types=1);

namespace App\DTO\StaticMap;

/**
 * Represents a polyline element on a static map.
 */
readonly class PolylineElement implements MapElementInterface
{
    /**
     * @param array<int, array{0: float, 1: float}> $coordinates Decoded coordinates
     */
    public function __construct(
        public string $data,
        public array $coordinates,
        public string $color = '#FF0000',
        public int $strokeWidth = 3,
    ) {
    }

    public function getType(): string
    {
        return 'polyline';
    }

    public function getCoordinates(): array
    {
        return $this->coordinates;
    }
}
