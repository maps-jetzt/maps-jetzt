<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use App\DTO\StaticMap\MapElementInterface;
use App\DTO\StaticMap\MarkerElement;
use App\DTO\StaticMap\PolylineElement;

/**
 * Factory for creating map elements from JSON data.
 */
class ElementFactory
{
    public function __construct(
        private readonly PolylineDecoder $polylineDecoder,
    ) {
    }

    /**
     * Parse an array of element data into typed DTOs.
     *
     * @param array<int, array<string, mixed>> $elementsData
     * @return array<int, MapElementInterface>
     * @throws \InvalidArgumentException
     */
    public function createFromArray(array $elementsData): array
    {
        $elements = [];

        foreach ($elementsData as $index => $data) {
            if (!is_array($data)) {
                throw new \InvalidArgumentException(sprintf('Element at index %d must be an array', $index));
            }

            if (!isset($data['type']) || !is_string($data['type'])) {
                throw new \InvalidArgumentException(sprintf('Element at index %d must have a "type" field', $index));
            }

            $elements[] = match ($data['type']) {
                'polyline' => $this->createPolyline($data, $index),
                'marker' => $this->createMarker($data, $index),
                default => throw new \InvalidArgumentException(
                    sprintf('Unknown element type "%s" at index %d', $data['type'], $index)
                ),
            };
        }

        return $elements;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createPolyline(array $data, int $index): PolylineElement
    {
        if (!isset($data['data']) || !is_string($data['data']) || $data['data'] === '') {
            throw new \InvalidArgumentException(
                sprintf('Polyline at index %d must have a non-empty "data" field', $index)
            );
        }

        $coordinates = $this->polylineDecoder->decode($data['data']);

        if (count($coordinates) < 2) {
            throw new \InvalidArgumentException(
                sprintf('Polyline at index %d must contain at least 2 points', $index)
            );
        }

        $color = $data['color'] ?? '#FF0000';
        if (!is_string($color) || !preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color)) {
            throw new \InvalidArgumentException(
                sprintf('Polyline at index %d has invalid color format', $index)
            );
        }

        $strokeWidth = $data['strokeWidth'] ?? 3;
        if (!is_int($strokeWidth) || $strokeWidth < 1 || $strokeWidth > 20) {
            throw new \InvalidArgumentException(
                sprintf('Polyline at index %d has invalid strokeWidth (must be 1-20)', $index)
            );
        }

        return new PolylineElement(
            data: $data['data'],
            coordinates: $coordinates,
            color: $color,
            strokeWidth: $strokeWidth,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createMarker(array $data, int $index): MarkerElement
    {
        if (!isset($data['lat']) || !is_numeric($data['lat'])) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d must have a numeric "lat" field', $index)
            );
        }

        if (!isset($data['lon']) || !is_numeric($data['lon'])) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d must have a numeric "lon" field', $index)
            );
        }

        $lat = (float) $data['lat'];
        $lon = (float) $data['lon'];

        if ($lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid latitude (must be -90 to 90)', $index)
            );
        }

        if ($lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid longitude (must be -180 to 180)', $index)
            );
        }

        $icon = $data['icon'] ?? 'location-dot';
        if (!is_string($icon)) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid icon name', $index)
            );
        }

        $iconStyle = $data['iconStyle'] ?? 'solid';
        if (!in_array($iconStyle, ['solid', 'regular', 'brands'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid iconStyle (must be solid, regular, or brands)', $index)
            );
        }

        $color = $data['color'] ?? '#FF0000';
        if (!is_string($color) || !preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color)) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid color format', $index)
            );
        }

        $iconColor = $data['iconColor'] ?? null;
        if ($iconColor !== null && (!is_string($iconColor) || !preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $iconColor))) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid iconColor format', $index)
            );
        }

        $size = $data['size'] ?? 32;
        if (!is_int($size) || $size < 16 || $size > 128) {
            throw new \InvalidArgumentException(
                sprintf('Marker at index %d has invalid size (must be 16-128)', $index)
            );
        }

        return new MarkerElement(
            lat: $lat,
            lon: $lon,
            icon: $icon,
            iconStyle: $iconStyle,
            color: $color,
            iconColor: $iconColor,
            size: $size,
        );
    }
}
