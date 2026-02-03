<?php declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    #[Route('/api/tiles/{z}/{x}/{y}.mvt', name: 'tiles', methods: ['GET'])]
    public function getTiles(int $z, int $x, int $y, Connection $connection): StreamedResponse
    {
        return new StreamedResponse(function () use ($connection, $z, $x, $y) {
            // SQL-Abfrage für die Tile-Daten
            $sql = '
                SELECT ST_AsMVT(tile) AS mvt
                FROM (
                    SELECT id, name, ST_AsMVTGeom(geom, tile_bbox(:z, :x, :y), 4096, 256, true) AS geom
                    FROM activity
                    WHERE geom && tile_bbox(:z, :x, :y)
                ) AS tile
            ';

            // Abfrage ausführen
            $stmt = $connection->executeQuery($sql, ['z' => $z, 'x' => $x, 'y' => $y]);

            // Das Ergebnis als Stream lesen
            $resource = $stmt->fetchOne();

            // Sicherstellen, dass es Daten gibt
            if ($resource === false) {
                throw new \RuntimeException('No data available for the requested tile.');
            }

            $outputStream = fopen('php://output', 'wb');

            stream_copy_to_stream($resource, $outputStream);
        }, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Content-Disposition' => 'inline; filename="tile.mvt"',
        ]);
    }

    #[Route('/api/tracks', name: 'api_tracks', methods: ['GET'])]
    public function getTracks(Connection $connection): JsonResponse
    {
        $tracks = $connection->fetchAllAssociative('
            SELECT id, name, ST_AsGeoJSON(geom) AS geojson
            FROM gpx_tracks
        ');

        $features = array_map(fn($track) => [
            'type' => 'Feature',
            'properties' => [
                'id' => $track['id'],
                'name' => $track['name'],
            ],
            'geometry' => json_decode($track['geojson']),
        ], $tracks);

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
