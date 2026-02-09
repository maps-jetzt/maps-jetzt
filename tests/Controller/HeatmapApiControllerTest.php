<?php declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HeatmapApiControllerTest extends WebTestCase
{
    /**
     * Google-encoded polylines covering various Hamburg areas.
     * Generated from real coordinates using the Google Encoded Polyline Algorithm.
     *
     *   1. Outer Alster loop
     *   2. HafenCity / Speicherstadt
     *   3. Stadtpark jog
     *   4. Elbe riverside (Blankenese to Oevelgoenne)
     *   5. Altonaer Balkon to Schanzenviertel
     */
    private const array POLYLINES = [
        'g||eI_c`|@wQg^_XwQoKwQvQgEf^fEf^vQvQ~WfE~WoKnKwQfEoK',
        'gfxeI_x}{@cBwQcB_XcB_XbBwQbBoKfEgEfEnKbBvQcB~WgE~WcB~W',
        '_~bfIg_e|@oKwQoKwQoK_XoKoKgEvQfE~WnKvQnKvQnKfEnKoK',
        'oeyeIwls{@gE_XkH_XkH_XkH_XkH_XgE_XgE_XcB_XcB_X',
        'oeyeIwls{@gEg^oKg^oKg^oKg^gEg^gEg^gEg^bBwQfEoK',
    ];

    private static function uniqueId(string $prefix): string
    {
        return $prefix . '-' . uniqid();
    }

    private static function json(string $method, string $url, array $data = []): array
    {
        return [$method, $url, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data)];
    }

    public function testCreateHeatmap(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('create');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame($identifier, $data['identifier']);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('createdAt', $data);
    }

    public function testCreateHeatmapWithViewport(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('viewport');

        $client->request(...self::json('POST', '/api/heatmaps', [
            'identifier' => $identifier,
            'center' => [9.99, 53.55],
            'zoom' => 14,
        ]));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame([9.99, 53.55], $data['center']);
        self::assertSame(14, $data['zoom']);
    }

    public function testCreateDuplicateHeatmapReturns409(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('dup');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));
        self::assertSame(409, $client->getResponse()->getStatusCode());
    }

    public function testCreateHeatmapWithoutIdentifierReturns400(): void
    {
        $client = static::createClient();

        $client->request(...self::json('POST', '/api/heatmaps', []));

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testUpdateHeatmapViewport(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('update');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        $client->request(...self::json('PATCH', "/api/heatmaps/{$identifier}", [
            'center' => [10.0, 53.5],
            'zoom' => 11,
        ]));

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertEqualsWithDelta(10.0, $data['center'][0], 0.001);
        self::assertEqualsWithDelta(53.5, $data['center'][1], 0.001);
        self::assertSame(11, $data['zoom']);
        self::assertArrayHasKey('updatedAt', $data);
    }

    public function testUpdateNonExistentHeatmapReturns404(): void
    {
        $client = static::createClient();

        $client->request(...self::json('PATCH', '/api/heatmaps/does-not-exist', ['zoom' => 5]));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testAddPolylinesReturnsHashes(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('hash');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));

        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => self::POLYLINES,
        ]));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame(count(self::POLYLINES), $data['polylinesAdded']);
        self::assertCount(count(self::POLYLINES), $data['hashes']);

        foreach ($data['hashes'] as $hash) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
        }
    }

    public function testAddPolylinesEmptyArrayReturns400(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('empty');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));

        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => [],
        ]));

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testAddPolylinesToNonExistentHeatmapReturns404(): void
    {
        $client = static::createClient();

        $client->request(...self::json('POST', '/api/heatmaps/does-not-exist-ever/polylines', [
            'polylines' => self::POLYLINES,
        ]));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testListPolylines(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('list');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));
        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => array_slice(self::POLYLINES, 0, 3),
        ]));

        $client->request('GET', "/api/heatmaps/{$identifier}/polylines");

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertCount(3, $data);

        foreach ($data as $polyline) {
            self::assertArrayHasKey('hash', $polyline);
            self::assertArrayHasKey('createdAt', $polyline);
            self::assertArrayHasKey('bbox', $polyline);
            self::assertCount(4, $polyline['bbox']);
        }
    }

    public function testListPolylinesOfNonExistentHeatmapReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/heatmaps/does-not-exist/polylines');

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testDeletePolyline(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('del-poly');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));
        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => [self::POLYLINES[0]],
        ]));

        $addData = json_decode($client->getResponse()->getContent(), true);
        $hash = $addData['hashes'][0];

        // Delete the polyline
        $client->request('DELETE', "/api/heatmaps/{$identifier}/polylines/{$hash}");
        self::assertSame(204, $client->getResponse()->getStatusCode());

        // Verify it's gone
        $client->request('GET', "/api/heatmaps/{$identifier}/polylines");
        $listData = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $listData);
    }

    public function testDeleteNonExistentPolylineReturns404(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('del-miss');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));

        $client->request('DELETE', "/api/heatmaps/{$identifier}/polylines/00000000000000000000000000000000");

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testGetTilesReturnsMvtWithCacheHeaders(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('tile');

        $client->request(...self::json('POST', '/api/heatmaps', ['identifier' => $identifier]));
        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => self::POLYLINES,
        ]));

        $client->request('GET', "/tiles/heatmaps/{$identifier}/12/2148/1350.mvt");

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/x-protobuf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('public', $response->headers->get('Cache-Control'));
        self::assertNotNull($response->headers->get('ETag'));
    }

    public function testFullWorkflow(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('hamburg-full');

        // 1. Create heatmap with viewport
        $client->request(...self::json('POST', '/api/heatmaps', [
            'identifier' => $identifier,
            'center' => [10.0, 53.55],
            'zoom' => 12,
        ]));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        // 2. Add polylines in two batches
        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => array_slice(self::POLYLINES, 0, 3),
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(3, $data['polylinesAdded']);
        self::assertCount(3, $data['hashes']);

        $client->request(...self::json('POST', "/api/heatmaps/{$identifier}/polylines", [
            'polylines' => array_slice(self::POLYLINES, 3),
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(2, $data['polylinesAdded']);

        // 3. List polylines
        $client->request('GET', "/api/heatmaps/{$identifier}/polylines");
        $list = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(5, $list);

        // 4. Delete one polyline
        $hash = $list[0]['hash'];
        $client->request('DELETE', "/api/heatmaps/{$identifier}/polylines/{$hash}");
        self::assertSame(204, $client->getResponse()->getStatusCode());

        // 5. Verify 4 remain
        $client->request('GET', "/api/heatmaps/{$identifier}/polylines");
        $list = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(4, $list);

        // 6. Update viewport
        $client->request(...self::json('PATCH', "/api/heatmaps/{$identifier}", [
            'center' => [9.99, 53.5],
            'zoom' => 14,
        ]));
        self::assertSame(200, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(14, $data['zoom']);

        // 7. Fetch a tile
        $client->request('GET', "/tiles/heatmaps/{$identifier}/12/2148/1350.mvt");
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
