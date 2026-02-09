<?php declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Activities')]
class ApiController extends AbstractController
{
    #[Route('/tiles/{z}/{x}/{y}.mvt', name: 'tiles', methods: ['GET'])]
    public function getTiles(int $z, int $x, int $y, Connection $connection): StreamedResponse
    {
        $etag = md5("activity-{$z}-{$x}-{$y}");

        return new StreamedResponse(function () use ($connection, $z, $x, $y) {
            $sql = '
                SELECT ST_AsMVT(tile) AS mvt
                FROM (
                    SELECT id, name, ST_AsMVTGeom(geom, ST_TileEnvelope(:z, :x, :y), 4096, 256, true) AS geom
                    FROM activity
                    WHERE geom && ST_TileEnvelope(:z, :x, :y)
                ) AS tile
            ';

            $stmt = $connection->executeQuery($sql, ['z' => $z, 'x' => $x, 'y' => $y]);

            $resource = $stmt->fetchOne();

            if (is_resource($resource)) {
                fpassthru($resource);
            } elseif ($resource !== false) {
                echo $resource;
            }
        }, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Content-Disposition' => 'inline; filename="tile.mvt"',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $etag,
        ]);
    }

    #[Route('/api/tracks', name: 'api_tracks', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get all tracks as GeoJSON FeatureCollection',
        responses: [
            new OA\Response(
                response: 200,
                description: 'GeoJSON FeatureCollection',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'FeatureCollection'),
                        new OA\Property(property: 'features', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'type', type: 'string', example: 'Feature'),
                                new OA\Property(property: 'properties', properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string'),
                                ], type: 'object'),
                                new OA\Property(property: 'geometry', type: 'object'),
                            ],
                            type: 'object',
                        )),
                    ],
                ),
            ),
        ],
    )]
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
