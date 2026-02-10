<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Heatmap;
use App\Entity\HeatmapPolyline;
use App\Service\GooglePolylineDecoder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Heatmaps')]
class HeatmapApiController extends AbstractController
{
    private function serializeHeatmap(Heatmap $heatmap): array
    {
        $data = [
            'id' => $heatmap->getId(),
            'identifier' => $heatmap->getIdentifier(),
            'createdAt' => $heatmap->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($heatmap->getUpdatedAt()) {
            $data['updatedAt'] = $heatmap->getUpdatedAt()->format(\DateTimeInterface::ATOM);
        }

        if ($heatmap->getCenterLon() !== null && $heatmap->getCenterLat() !== null) {
            $data['center'] = [$heatmap->getCenterLon(), $heatmap->getCenterLat()];
        }

        if ($heatmap->getZoom() !== null) {
            $data['zoom'] = $heatmap->getZoom();
        }

        return $data;
    }

    private function applyViewport(Heatmap $heatmap, array $data): void
    {
        if (array_key_exists('center', $data) && is_array($data['center']) && count($data['center']) === 2) {
            $heatmap->setCenterLon((float) $data['center'][0]);
            $heatmap->setCenterLat((float) $data['center'][1]);
        }

        if (array_key_exists('zoom', $data)) {
            $heatmap->setZoom($data['zoom'] !== null ? (int) $data['zoom'] : null);
        }
    }

    #[Route('/api/heatmaps', name: 'api_heatmap_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new heatmap',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['identifier'],
                properties: [
                    new OA\Property(property: 'identifier', type: 'string'),
                    new OA\Property(property: 'center', type: 'array', items: new OA\Items(type: 'number'), description: '[lon, lat]'),
                    new OA\Property(property: 'zoom', type: 'integer'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Heatmap created'),
            new OA\Response(response: 400, description: 'Missing identifier'),
            new OA\Response(response: 409, description: 'Identifier already exists'),
        ],
    )]
    public function createHeatmap(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $identifier = $data['identifier'] ?? null;

        if (!$identifier) {
            return $this->json(['error' => 'identifier is required'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $em->getRepository(Heatmap::class)->findOneBy(['identifier' => $identifier]);
        if ($existing) {
            return $this->json(['error' => 'Heatmap with this identifier already exists'], Response::HTTP_CONFLICT);
        }

        $heatmap = new Heatmap();
        $heatmap->setIdentifier($identifier);
        $this->applyViewport($heatmap, $data);

        $em->persist($heatmap);
        $em->flush();

        return $this->json($this->serializeHeatmap($heatmap), Response::HTTP_CREATED);
    }

    #[Route('/api/heatmaps/{identifier}', name: 'api_heatmap_update', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Update heatmap properties',
        parameters: [
            new OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'center', type: 'array', items: new OA\Items(type: 'number'), description: '[lon, lat]'),
                    new OA\Property(property: 'zoom', type: 'integer'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Heatmap updated'),
            new OA\Response(response: 404, description: 'Heatmap not found'),
        ],
    )]
    public function updateHeatmap(string $identifier, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $heatmap = $em->getRepository(Heatmap::class)->findOneBy(['identifier' => $identifier]);
        if (!$heatmap) {
            return $this->json(['error' => 'Heatmap not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $this->applyViewport($heatmap, $data);

        $em->flush();

        return $this->json($this->serializeHeatmap($heatmap));
    }

    #[Route('/api/heatmaps/{identifier}/polylines', name: 'api_heatmap_polylines', methods: ['POST'])]
    #[OA\Post(
        summary: 'Add polylines to a heatmap',
        parameters: [
            new OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['polylines'],
                properties: [
                    new OA\Property(property: 'polylines', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Polylines added', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'identifier', type: 'string'),
                    new OA\Property(property: 'polylinesAdded', type: 'integer'),
                    new OA\Property(property: 'hashes', type: 'array', items: new OA\Items(type: 'string')),
                ],
            )),
            new OA\Response(response: 400, description: 'Missing polylines'),
            new OA\Response(response: 404, description: 'Heatmap not found'),
        ],
    )]
    public function addPolylines(
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        GooglePolylineDecoder $decoder,
    ): JsonResponse {
        $heatmap = $em->getRepository(Heatmap::class)->findOneBy(['identifier' => $identifier]);
        if (!$heatmap) {
            return $this->json(['error' => 'Heatmap not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $polylines = $data['polylines'] ?? [];

        if (empty($polylines)) {
            return $this->json(['error' => 'polylines array is required'], Response::HTTP_BAD_REQUEST);
        }

        $conn = $em->getConnection();
        $count = 0;
        $hashes = [];

        foreach ($polylines as $encoded) {
            $hash = md5($encoded);
            $wkt = $decoder->decodeToWkt($encoded);

            $result = $conn->fetchOne(
                'SELECT ST_AsEWKT(ST_Transform(ST_GeomFromText(:wkt, 4326), 3857))',
                ['wkt' => $wkt]
            );

            $polyline = new HeatmapPolyline();
            $polyline->setHeatmap($heatmap);
            $polyline->setHash($hash);
            $polyline->setGeom($result);

            $em->persist($polyline);
            $hashes[] = $hash;
            $count++;

            if ($count % 50 === 0) {
                $em->flush();
            }
        }

        $heatmap->touch();
        $em->flush();

        return $this->json([
            'identifier' => $identifier,
            'polylinesAdded' => $count,
            'hashes' => $hashes,
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/heatmaps/{identifier}/polylines', name: 'api_heatmap_polylines_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all polylines of a heatmap',
        parameters: [
            new OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of polylines', content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'hash', type: 'string'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'bbox', type: 'array', items: new OA\Items(type: 'number')),
                    ],
                ),
            )),
            new OA\Response(response: 404, description: 'Heatmap not found'),
        ],
    )]
    public function listPolylines(string $identifier, Connection $connection): JsonResponse
    {
        $heatmapId = $connection->fetchOne(
            'SELECT id FROM heatmap WHERE identifier = :identifier',
            ['identifier' => $identifier],
        );

        if ($heatmapId === false) {
            return $this->json(['error' => 'Heatmap not found'], Response::HTTP_NOT_FOUND);
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT hp.hash,
                    hp.created_at,
                    ST_XMin(ST_Transform(ST_Envelope(hp.geom), 4326)) AS min_lon,
                    ST_YMin(ST_Transform(ST_Envelope(hp.geom), 4326)) AS min_lat,
                    ST_XMax(ST_Transform(ST_Envelope(hp.geom), 4326)) AS max_lon,
                    ST_YMax(ST_Transform(ST_Envelope(hp.geom), 4326)) AS max_lat
             FROM heatmap_polyline hp
             WHERE hp.heatmap_id = :heatmapId
             ORDER BY hp.created_at DESC',
            ['heatmapId' => $heatmapId],
        );

        $result = array_map(fn(array $row) => [
            'hash' => $row['hash'],
            'createdAt' => $row['created_at'],
            'bbox' => [
                (float) $row['min_lon'],
                (float) $row['min_lat'],
                (float) $row['max_lon'],
                (float) $row['max_lat'],
            ],
        ], $rows);

        return $this->json($result);
    }

    #[Route('/api/heatmaps/{identifier}/polylines/{hash}', name: 'api_heatmap_polyline_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a polyline by hash',
        parameters: [
            new OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Polyline deleted'),
            new OA\Response(response: 404, description: 'Heatmap or polyline not found'),
        ],
    )]
    public function deletePolyline(string $identifier, string $hash, Connection $connection): Response
    {
        $heatmapId = $connection->fetchOne(
            'SELECT id FROM heatmap WHERE identifier = :identifier',
            ['identifier' => $identifier],
        );

        if ($heatmapId === false) {
            return $this->json(['error' => 'Heatmap not found'], Response::HTTP_NOT_FOUND);
        }

        $affected = $connection->executeStatement(
            'DELETE FROM heatmap_polyline WHERE heatmap_id = :heatmapId AND hash = :hash',
            ['heatmapId' => $heatmapId, 'hash' => $hash],
        );

        if ($affected === 0) {
            return $this->json(['error' => 'Polyline not found'], Response::HTTP_NOT_FOUND);
        }

        $connection->executeStatement(
            'UPDATE heatmap SET updated_at = NOW() WHERE id = :id',
            ['id' => $heatmapId],
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/tiles/heatmaps/{identifier}/{z}/{x}/{y}.mvt', name: 'heatmap_tiles', methods: ['GET'])]
    public function getTiles(
        string $identifier,
        int $z,
        int $x,
        int $y,
        Connection $connection,
        Request $request,
    ): Response {
        $timestamp = $connection->fetchOne(
            'SELECT COALESCE(updated_at, created_at) FROM heatmap WHERE identifier = :identifier',
            ['identifier' => $identifier],
        );

        $etag = '"' . md5("{$identifier}-{$z}-{$x}-{$y}-{$timestamp}") . '"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return new Response(null, Response::HTTP_NOT_MODIFIED, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        try {
            $sql = "
                SELECT ST_AsMVT(tile, 'heatmap', 4096, 'geom') AS mvt
                FROM (
                    SELECT hp.id, hp.hash,
                           ST_AsMVTGeom(
                               hp.geom,
                               ST_TileEnvelope(:z, :x, :y),
                               4096,
                               256,
                               true
                           ) AS geom
                    FROM heatmap_polyline hp
                    JOIN heatmap h ON h.id = hp.heatmap_id
                    WHERE h.identifier = :identifier
                      AND ST_Intersects(
                          hp.geom,
                          ST_TileEnvelope(:z, :x, :y)
                      )
                ) AS tile
            ";

            $stmt = $connection->executeQuery($sql, [
                'z' => $z,
                'x' => $x,
                'y' => $y,
                'identifier' => $identifier,
            ]);

            $mvt = $stmt->fetchOne();

            if (is_resource($mvt)) {
                $mvt = stream_get_contents($mvt);
            }

            if (!$mvt) {
                $mvt = '';
            }
        } catch (\Throwable) {
            $mvt = '';
        }

        return new Response($mvt, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Content-Length' => strlen($mvt),
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $etag,
        ]);
    }
}
