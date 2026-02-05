<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StaticMap\ElementFactory;
use App\Service\StaticMap\StaticMapGenerator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StaticMapController extends AbstractController
{
    private const int MAX_WIDTH = 2000;
    private const int MAX_HEIGHT = 2000;
    private const int MIN_SIZE = 100;
    private const int MAX_ELEMENTS = 100;
    private const string MAPS_DIR = 'maps';

    public function __construct(
        private readonly StaticMapGenerator $generator,
        private readonly ElementFactory $elementFactory,
        private readonly string $publicDir,
    ) {
    }

    #[Route('/api/static-map', name: 'api_static_map', methods: ['POST'])]
    #[OA\Post(
        summary: 'Generate a static map image with elements',
        description: 'Creates a PNG image showing polylines and markers on OpenStreetMap tiles',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['width', 'height', 'elements'],
                properties: [
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
                        property: 'elements',
                        type: 'array',
                        description: 'Array of map elements (polylines and markers)',
                        items: new OA\Items(
                            oneOf: [
                                new OA\Schema(
                                    title: 'Polyline',
                                    required: ['type', 'data'],
                                    properties: [
                                        new OA\Property(property: 'type', type: 'string', enum: ['polyline']),
                                        new OA\Property(
                                            property: 'data',
                                            type: 'string',
                                            description: 'Google Encoded Polyline string',
                                            example: 'myyeI}r_|@aCyAg@qPOkYO_H_LtBiKtKaG|P_GlSaEhZ}AvXxNzGxGaRuL_JeMmGaFdNkFhDgGmE'
                                        ),
                                        new OA\Property(
                                            property: 'color',
                                            type: 'string',
                                            description: 'Line color as hex string',
                                            example: '#FF0000',
                                            default: '#FF0000'
                                        ),
                                        new OA\Property(
                                            property: 'strokeWidth',
                                            type: 'integer',
                                            description: 'Line width in pixels (1-20)',
                                            example: 3,
                                            default: 3
                                        ),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Schema(
                                    title: 'Marker',
                                    required: ['type', 'lat', 'lon'],
                                    properties: [
                                        new OA\Property(property: 'type', type: 'string', enum: ['marker']),
                                        new OA\Property(
                                            property: 'lat',
                                            type: 'number',
                                            format: 'float',
                                            description: 'Latitude (-90 to 90)',
                                            example: 53.549484
                                        ),
                                        new OA\Property(
                                            property: 'lon',
                                            type: 'number',
                                            format: 'float',
                                            description: 'Longitude (-180 to 180)',
                                            example: 9.9972656
                                        ),
                                        new OA\Property(
                                            property: 'icon',
                                            type: 'string',
                                            description: 'FontAwesome icon name',
                                            example: 'location-dot',
                                            default: 'location-dot'
                                        ),
                                        new OA\Property(
                                            property: 'iconStyle',
                                            type: 'string',
                                            enum: ['solid', 'regular', 'brands'],
                                            description: 'FontAwesome icon style',
                                            default: 'solid'
                                        ),
                                        new OA\Property(
                                            property: 'color',
                                            type: 'string',
                                            description: 'Marker pin color as hex string',
                                            example: '#FF0000',
                                            default: '#FF0000'
                                        ),
                                        new OA\Property(
                                            property: 'iconColor',
                                            type: 'string',
                                            description: 'Icon color as hex string (defaults to white)',
                                            example: '#FFFFFF',
                                            default: '#FFFFFF'
                                        ),
                                        new OA\Property(
                                            property: 'size',
                                            type: 'integer',
                                            description: 'Marker size in pixels (16-128)',
                                            example: 32,
                                            default: 32
                                        ),
                                    ],
                                    type: 'object'
                                ),
                            ]
                        ),
                        example: [
                            ['type' => 'polyline', 'data' => 'myyeI}r_|@aCyAg@qPOkYO_H_LtBiKtKaG|P_GlSaEhZ}AvXxNzGxGaRuL_JeMmGaFdNkFhDgGmE', 'color' => '#FF0000'],
                            ['type' => 'marker', 'lat' => 53.549484, 'lon' => 9.9972656, 'icon' => 'location-dot'],
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'URL to the generated map image',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'url', type: 'string', example: '/maps/a1b2c3d4e5f6.png'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'elements is required'),
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
        if (!isset($data['width']) || !is_int($data['width'])) {
            return $this->jsonError('width is required and must be an integer');
        }

        if (!isset($data['height']) || !is_int($data['height'])) {
            return $this->jsonError('height is required and must be an integer');
        }

        if (!isset($data['elements']) || !is_array($data['elements'])) {
            return $this->jsonError('elements is required and must be an array');
        }

        $width = $data['width'];
        $height = $data['height'];
        $elementsData = $data['elements'];

        // Validate dimensions
        if ($width < self::MIN_SIZE || $width > self::MAX_WIDTH) {
            return $this->jsonError(sprintf('width must be between %d and %d', self::MIN_SIZE, self::MAX_WIDTH));
        }

        if ($height < self::MIN_SIZE || $height > self::MAX_HEIGHT) {
            return $this->jsonError(sprintf('height must be between %d and %d', self::MIN_SIZE, self::MAX_HEIGHT));
        }

        // Validate elements array
        if (count($elementsData) === 0) {
            return $this->jsonError('elements must contain at least one element');
        }

        if (count($elementsData) > self::MAX_ELEMENTS) {
            return $this->jsonError(sprintf('elements must not contain more than %d elements', self::MAX_ELEMENTS));
        }

        try {
            // Parse elements
            $elements = $this->elementFactory->createFromArray($elementsData);

            // Generate MD5 hash from request parameters
            $hash = md5(json_encode([
                'width' => $width,
                'height' => $height,
                'elements' => $elementsData,
            ]));
            $filename = $hash . '.png';

            // Ensure maps directory exists
            $mapsDir = $this->publicDir . '/' . self::MAPS_DIR;
            if (!is_dir($mapsDir)) {
                mkdir($mapsDir, 0755, true);
            }

            $filePath = $mapsDir . '/' . $filename;

            // Only generate if file doesn't exist (caching)
            if (!file_exists($filePath)) {
                $image = $this->generator->generateWithElements($elements, $width, $height);
                imagepng($image, $filePath);
                imagedestroy($image);
            }

            $url = $this->generateUrl(
                'static_map_image',
                ['filename' => $filename],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            return $this->json(['url' => $url]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    #[Route('/maps/{filename}', name: 'static_map_image', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a generated static map image',
        responses: [
            new OA\Response(
                response: 200,
                description: 'The PNG image',
                content: new OA\MediaType(mediaType: 'image/png')
            ),
            new OA\Response(response: 404, description: 'Image not found'),
        ]
    )]
    #[OA\Tag(name: 'Static Map')]
    public function getImage(string $filename): Response
    {
        // Validate filename format (MD5 hash + .png)
        if (!preg_match('/^[a-f0-9]{32}\.png$/', $filename)) {
            throw $this->createNotFoundException();
        }

        $filePath = $this->publicDir . '/' . self::MAPS_DIR . '/' . $filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($filePath, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    private function jsonError(string $message): Response
    {
        return $this->json(['error' => $message], Response::HTTP_BAD_REQUEST);
    }
}
