<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StaticMap\StaticMapGenerator;
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
