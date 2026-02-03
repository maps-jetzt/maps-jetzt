<?php

declare(strict_types=1);

namespace App\Service\StaticMap;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches map tiles from OpenStreetMap tile server.
 */
class TileFetcher
{
    private const string USER_AGENT = 'MapsJetzt/1.0 (Static Map Generator)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $tileServerUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    ) {
    }

    /**
     * Fetch multiple tiles in parallel.
     *
     * @param array<array{x: int, y: int}> $tiles
     * @return array<string, \GdImage> Map of "x,y" => GdImage
     */
    public function fetchTiles(array $tiles, int $zoom): array
    {
        $responses = [];
        $results = [];

        // Start all requests in parallel
        foreach ($tiles as $tile) {
            $url = $this->buildTileUrl($tile['x'], $tile['y'], $zoom);
            $key = $tile['x'] . ',' . $tile['y'];
            $responses[$key] = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                ],
            ]);
        }

        // Collect responses
        foreach ($responses as $key => $response) {
            try {
                $content = $response->getContent();
                $image = @imagecreatefromstring($content);

                if ($image !== false) {
                    $results[$key] = $image;
                } else {
                    $results[$key] = $this->createPlaceholderTile();
                }
            } catch (\Throwable) {
                $results[$key] = $this->createPlaceholderTile();
            }
        }

        return $results;
    }

    /**
     * Create a gray placeholder tile for failed requests.
     */
    private function createPlaceholderTile(): \GdImage
    {
        $image = imagecreatetruecolor(256, 256);
        $gray = imagecolorallocate($image, 200, 200, 200);
        imagefill($image, 0, 0, $gray);

        return $image;
    }

    private function buildTileUrl(int $x, int $y, int $zoom): string
    {
        return str_replace(
            ['{z}', '{x}', '{y}'],
            [(string) $zoom, (string) $x, (string) $y],
            $this->tileServerUrl,
        );
    }
}
