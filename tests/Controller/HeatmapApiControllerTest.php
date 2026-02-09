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

    public function testCreateHeatmap(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('create');

        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame($identifier, $data['identifier']);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('createdAt', $data);
    }

    public function testCreateDuplicateHeatmapReturns409(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('dup');

        // Create first
        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        // Create duplicate
        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));
        self::assertSame(409, $client->getResponse()->getStatusCode());
    }

    public function testCreateHeatmapWithoutIdentifierReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testAddPolylines(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('poly');

        // Create heatmap first
        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        // Add all Hamburg polylines
        $client->request('POST', "/api/heatmaps/{$identifier}/polylines", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['polylines' => self::POLYLINES]));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertSame($identifier, $data['identifier']);
        self::assertSame(count(self::POLYLINES), $data['polylinesAdded']);
    }

    public function testAddPolylinesEmptyArrayReturns400(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('empty');

        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));

        $client->request('POST', "/api/heatmaps/{$identifier}/polylines", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['polylines' => []]));

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testAddPolylinesToNonExistentHeatmapReturns404(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/heatmaps/does-not-exist-ever/polylines', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['polylines' => self::POLYLINES]));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testGetTilesReturnsMvt(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('tile');

        // Create heatmap with polylines first
        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));

        $client->request('POST', "/api/heatmaps/{$identifier}/polylines", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['polylines' => self::POLYLINES]));

        // Request tile at zoom 12 covering Hamburg center
        $client->request('GET', "/api/heatmaps/{$identifier}/tiles/12/2148/1350.mvt");

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/x-protobuf', $response->headers->get('Content-Type'));
    }

    public function testFullWorkflow(): void
    {
        $client = static::createClient();
        $identifier = self::uniqueId('hamburg-full');

        // 1. Create heatmap
        $client->request('POST', '/api/heatmaps', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['identifier' => $identifier]));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        // 2. Add polylines in two batches
        $client->request('POST', "/api/heatmaps/{$identifier}/polylines", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['polylines' => array_slice(self::POLYLINES, 0, 3)]));
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(3, $data['polylinesAdded']);

        $client->request('POST', "/api/heatmaps/{$identifier}/polylines", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['polylines' => array_slice(self::POLYLINES, 3)]));
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(2, $data['polylinesAdded']);

        // 3. Fetch a tile
        $client->request('GET', "/api/heatmaps/{$identifier}/tiles/12/2148/1350.mvt");
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
