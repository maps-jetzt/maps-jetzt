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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Heatmaps')]
class HeatmapApiController extends AbstractController
{
    #[Route('/api/heatmaps', name: 'api_heatmap_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new heatmap',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['identifier'],
                properties: [
                    new OA\Property(property: 'identifier', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Heatmap created', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'identifier', type: 'string'),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                ],
            )),
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

        $em->persist($heatmap);
        $em->flush();

        return $this->json([
            'id' => $heatmap->getId(),
            'identifier' => $heatmap->getIdentifier(),
            'createdAt' => $heatmap->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
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

        foreach ($polylines as $encoded) {
            $wkt = $decoder->decodeToWkt($encoded);

            $result = $conn->fetchOne(
                'SELECT ST_AsText(ST_Transform(ST_GeomFromText(:wkt), 3857))',
                ['wkt' => $wkt]
            );

            $polyline = new HeatmapPolyline();
            $polyline->setHeatmap($heatmap);
            $polyline->setGeom($result);

            $em->persist($polyline);
            $count++;

            if ($count % 50 === 0) {
                $em->flush();
            }
        }

        $em->flush();

        return $this->json([
            'identifier' => $identifier,
            'polylinesAdded' => $count,
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/heatmaps/{identifier}/tiles/{z}/{x}/{y}.mvt', name: 'api_heatmap_tiles', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get heatmap MVT tiles',
        parameters: [
            new OA\Parameter(name: 'identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'z', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'x', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'y', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'MVT tile', content: new OA\MediaType(mediaType: 'application/x-protobuf')),
        ],
    )]
    public function getTiles(
        string $identifier,
        int $z,
        int $x,
        int $y,
        Connection $connection,
    ): StreamedResponse {
        return new StreamedResponse(function () use ($connection, $identifier, $z, $x, $y) {
            $sql = "
                SELECT ST_AsMVT(tile, 'heatmap', 4096, 'geom') AS mvt
                FROM (
                    SELECT hp.id,
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

            $resource = $stmt->fetchOne();

            if (is_resource($resource)) {
                fpassthru($resource);
            } elseif ($resource !== false) {
                echo $resource;
            }
        }, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Content-Disposition' => 'inline; filename="tile.mvt"',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
