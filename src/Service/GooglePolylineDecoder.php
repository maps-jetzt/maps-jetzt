<?php declare(strict_types=1);

namespace App\Service;

class GooglePolylineDecoder
{
    /**
     * Decodes a Google-encoded polyline string into an array of [lng, lat] pairs.
     *
     * @return list<array{0: float, 1: float}>
     */
    public function decode(string $encoded): array
    {
        $points = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            foreach ([&$lat, &$lng] as &$coord) {
                $shift = 0;
                $result = 0;

                do {
                    $byte = ord($encoded[$index++]) - 63;
                    $result |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20);

                $coord += ($result & 1) ? ~($result >> 1) : ($result >> 1);
            }

            $points[] = [round($lng / 1e5, 5), round($lat / 1e5, 5)];
        }

        return $points;
    }

    /**
     * Decodes a polyline and returns a WKT LINESTRING with SRID=4326.
     */
    public function decodeToWkt(string $encoded): string
    {
        $points = $this->decode($encoded);
        $coords = implode(', ', array_map(fn(array $p) => $p[0] . ' ' . $p[1], $points));

        return 'SRID=4326;LINESTRING(' . $coords . ')';
    }
}
