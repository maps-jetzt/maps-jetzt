<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

/**
 * Decodes Google Encoded Polyline strings into coordinate arrays.
 */
class PolylineDecoder
{
    /**
     * Decode a Google Encoded Polyline string.
     *
     * @param string $encoded The encoded polyline string
     * @return array<int, array{0: float, 1: float}> Array of [lat, lon] coordinates
     */
    public function decode(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $length = strlen($encoded);
        $lat = 0;
        $lon = 0;

        while ($index < $length) {
            // Decode latitude
            $lat += $this->decodeValue($encoded, $index);
            // Decode longitude
            $lon += $this->decodeValue($encoded, $index);

            $coordinates[] = [$lat / 1e5, $lon / 1e5];
        }

        return $coordinates;
    }

    /**
     * Decode a single value from the encoded string.
     */
    private function decodeValue(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }
}
