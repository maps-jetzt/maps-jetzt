<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StaticMap\StaticMapGenerator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class StaticMapController extends AbstractController
{
    private const int MAX_WIDTH = 2000;
    private const int MAX_HEIGHT = 2000;
    private const int MIN_SIZE = 100;
    private const int MAX_STROKE_WIDTH = 20;

    public function __construct(
        private readonly StaticMapGenerator $generator,
    ) {
    }

    #[Route('/api/static-map', name: 'api_static_map', methods: ['POST'])]
    #[OA\Post(
        summary: 'Generate a static map image with a route',
        description: 'Creates a PNG image showing a route on OpenStreetMap tiles',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['polyline', 'width', 'height'],
                properties: [
                    new OA\Property(
                        property: 'polyline',
                        type: 'string',
                        description: 'Google Encoded Polyline string',
                        example: '_p~iF~ps|U_ulLnnqC_mqNvxq`@'
                    ),
                    new OA\Property(
                        property: 'width',
                        type: 'integer',
                        description: 'Image width in pixels (100-2000)',
                        example: 800
                    ),
                    new OA\Property(
                        property: 'height',
                        type: 'integer',
                        description: 'Image height in pixels (100-2000)',
                        example: 600
                    ),
                    new OA\Property(
                        property: 'color',
                        type: 'string',
                        description: 'Route color as hex string',
                        example: '#FF5500',
                        default: '#FF0000'
                    ),
                    new OA\Property(
                        property: 'strokeWidth',
                        type: 'integer',
                        description: 'Route line width in pixels (1-20)',
                        example: 4,
                        default: 3
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'PNG image of the map with route',
                content: new OA\MediaType(
                    mediaType: 'image/png'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'polyline is required'),
                    ]
                )
            ),
        ]
    )]
    #[OA\Tag(name: 'Static Map')]
    public function generateMap(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->jsonError('Invalid JSON body');
        }

        // Validate required fields
        if (!isset($data['polyline']) || !is_string($data['polyline']) || $data['polyline'] === '') {
            return $this->jsonError('polyline is required and must be a non-empty string');
        }

        if (!isset($data['width']) || !is_int($data['width'])) {
            return $this->jsonError('width is required and must be an integer');
        }

        if (!isset($data['height']) || !is_int($data['height'])) {
            return $this->jsonError('height is required and must be an integer');
        }

        $polyline = $data['polyline'];
        $width = $data['width'];
        $height = $data['height'];
        $color = $data['color'] ?? '#FF0000';
        $strokeWidth = $data['strokeWidth'] ?? 3;

        // Validate dimensions
        if ($width < self::MIN_SIZE || $width > self::MAX_WIDTH) {
            return $this->jsonError(sprintf('width must be between %d and %d', self::MIN_SIZE, self::MAX_WIDTH));
        }

        if ($height < self::MIN_SIZE || $height > self::MAX_HEIGHT) {
            return $this->jsonError(sprintf('height must be between %d and %d', self::MIN_SIZE, self::MAX_HEIGHT));
        }

        // Validate color
        if (!is_string($color) || !preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color)) {
            return $this->jsonError('color must be a valid hex color (e.g., #FF0000 or #F00)');
        }

        // Validate stroke width
        if (!is_int($strokeWidth) || $strokeWidth < 1 || $strokeWidth > self::MAX_STROKE_WIDTH) {
            return $this->jsonError(sprintf('strokeWidth must be between 1 and %d', self::MAX_STROKE_WIDTH));
        }

        try {
            $image = $this->generator->generate($polyline, $width, $height, $color, $strokeWidth);

            return new StreamedResponse(
                function () use ($image): void {
                    imagepng($image);
                    imagedestroy($image);
                },
                Response::HTTP_OK,
                [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                ],
            );
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    private function jsonError(string $message): Response
    {
        return $this->json(['error' => $message], Response::HTTP_BAD_REQUEST);
    }
}
