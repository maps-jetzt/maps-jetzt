<?php declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StaticMapControllerTest extends WebTestCase
{
    public function testDeleteStaticMap(): void
    {
        $client = static::createClient();
        $publicDir = static::getContainer()->getParameter('kernel.project_dir') . '/public';
        $mapsDir = $publicDir . '/maps';

        if (!is_dir($mapsDir)) {
            mkdir($mapsDir, 0755, true);
        }

        // Create a fake map file
        $hash = md5('test-delete-' . uniqid());
        $filename = $hash . '.png';
        file_put_contents($mapsDir . '/' . $filename, 'fake-png');

        $client->request('DELETE', "/api/maps/{$filename}");

        self::assertSame(204, $client->getResponse()->getStatusCode());
        self::assertFileDoesNotExist($mapsDir . '/' . $filename);
    }

    public function testDeleteNonExistentStaticMapReturns404(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/maps/00000000000000000000000000000000.png');

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testDeleteStaticMapInvalidFilenameReturns404(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/maps/not-a-valid-hash.png');

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }
}
