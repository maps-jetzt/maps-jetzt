# maps.jetzt

A geospatial web application for visualizing GPS activities, heatmaps, and generating static map images. Built with Symfony, PostGIS, and OpenLayers.

## Features

- **Interactive Map** -- Browse GPS activities on a vector tile map powered by OpenLayers and MVT
- **Heatmap Visualization** -- Aggregate encoded polylines into heatmaps with semi-transparent rendering
- **Static Map Generation** -- Create PNG images with polylines and FontAwesome markers via API
- **GPX Import** -- Batch import GPX files into the database via CLI
- **API Documentation** -- Swagger UI at `/api/doc` with full OpenAPI specs

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Symfony 8.0, PHP 8.5 |
| Database | PostgreSQL 15, PostGIS 3.3 |
| ORM | Doctrine with `jsor/doctrine-postgis` |
| Frontend | OpenLayers 10, Stimulus, Webpack Encore |
| API Docs | NelmioApiDocBundle (Swagger/OpenAPI) |
| Map Tiles | OpenStreetMap via `tile.openstreetmap.org` |

## Requirements

- PHP 8.5+ with GD extension
- PostgreSQL 15+ with PostGIS
- Node.js 18+
- Composer

## Setup

### 1. Clone and install dependencies

```bash
git clone git@github.com:maps-jetzt/maps-jetzt.git
cd maps-jetzt

composer install
npm install
```

### 2. Start the database

```bash
docker compose up -d postgis
```

This starts a PostGIS container on port **25432** with database `gis`, user `docker`, password `docker`.

### 3. Configure environment

The default `.env` is pre-configured for the Docker setup:

```
DATABASE_URL="pgsql://docker:docker@127.0.0.1:25432/gis?serverVersion=15&charset=utf8"
```

Override locally with `.env.local` if needed.

### 4. Create the database schema

```bash
php bin/console doctrine:schema:update --dump-sql
```

Review the output and run the relevant `CREATE TABLE` / `ALTER TABLE` statements manually:

```bash
php bin/console dbal:run-sql "CREATE TABLE activity (...)"
```

> **Note:** `doctrine:schema:update --force` may fail because it tries to drop `pg_cron` sequences. Always use `--dump-sql` first and apply selectively.

For heatmap polylines, create a spatial index manually:

```sql
CREATE INDEX heatmap_polyline_geom_idx ON heatmap_polyline USING GIST (geom);
```

### 5. Build frontend assets

```bash
npm run dev       # Development build with watch
npm run build     # Production build
```

### 6. Start the web server

```bash
symfony serve
```

The application is now available at `https://127.0.0.1:8000`.

## Usage

### Web Interface

| Route | Description |
|---|---|
| `/map` | Interactive map showing all imported activities |
| `/heatmap/{identifier}` | Heatmap visualization for a specific identifier |
| `/api/doc` | Swagger UI with API documentation |

### CLI Commands

**Import GPX files:**

```bash
php bin/console app:import-gpx /path/to/gpx/files
```

Reads all `.gpx` files from the given directory and creates `Activity` entities with LINESTRING geometry.

## API

All endpoints are documented at `/api/doc`. Summary:

### Activities

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/tiles/{z}/{x}/{y}.mvt` | MVT vector tiles for activities |
| `GET` | `/api/tracks` | All tracks as GeoJSON FeatureCollection |

### Heatmaps

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/heatmaps` | Create a new heatmap |
| `POST` | `/api/heatmaps/{identifier}/polylines` | Add Google-encoded polylines to a heatmap |
| `GET` | `/api/heatmaps/{identifier}/tiles/{z}/{x}/{y}.mvt` | MVT vector tiles for a heatmap |

**Create a heatmap and add polylines:**

```bash
# Create heatmap
curl -X POST https://127.0.0.1:8000/api/heatmaps \
  -H 'Content-Type: application/json' \
  -d '{"identifier": "my-heatmap"}'

# Add polylines (Google Encoded Polyline format)
curl -X POST https://127.0.0.1:8000/api/heatmaps/my-heatmap/polylines \
  -H 'Content-Type: application/json' \
  -d '{"polylines": ["myyeI}r_|@aCyAg@qP..."]}'
```

### Static Map

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/static-map` | Generate a PNG map image with polylines and markers |

**Generate a static map:**

```bash
curl -X POST https://127.0.0.1:8000/api/static-map \
  -H 'Content-Type: application/json' \
  -d '{
    "width": 800,
    "height": 600,
    "elements": [
      {
        "type": "polyline",
        "data": "myyeI}r_|@aCyAg@qPOkYO_H_LtB",
        "color": "#FF0000",
        "strokeWidth": 3
      },
      {
        "type": "marker",
        "lat": 53.549,
        "lon": 9.997,
        "icon": "location-dot",
        "color": "#FF0000"
      }
    ]
  }'
```

Returns a JSON response with the URL to the generated PNG image. Images are cached by content hash in `public/maps/`.

## Project Structure

```
src/
  Controller/
    ApiController.php             # Activity tiles + tracks
    HeatmapApiController.php      # Heatmap CRUD + tiles
    HeatmapController.php         # Heatmap frontend page
    MapController.php             # Map frontend page
    StaticMapController.php       # Static map generation API
  Entity/
    Activity.php                  # GPS track (LINESTRING, SRID 4326)
    Heatmap.php                   # Heatmap container
    HeatmapPolyline.php           # Polyline in heatmap (LINESTRING, SRID 3857)
  Service/
    GooglePolylineDecoder.php     # Decodes Google Encoded Polylines
    StaticMap/                    # Static map generation pipeline
      StaticMapGenerator.php      # Orchestrator
      TileCalculator.php          # Web Mercator math
      TileFetcher.php             # Parallel OSM tile fetching
      ImageComposer.php           # GD image composition
      MarkerRenderer.php          # FontAwesome marker rendering
      PolylineDecoder.php         # Polyline to pixel coordinates
      BoundingBox.php             # Geographic bounds calculation
      ElementFactory.php          # JSON to DTO conversion
      FontAwesomeIconMap.php      # Icon name to Unicode mapping
  DTO/StaticMap/
    MapElementInterface.php
    PolylineElement.php
    MarkerElement.php
  Command/
    ImportGpxCommand.php          # GPX file import CLI

assets/
  controllers/
    map_controller.js             # Stimulus: interactive map
    heatmap_controller.js         # Stimulus: heatmap visualization
  fonts/                          # FontAwesome TTF files
```

## Database Notes

- **Activity** geometry is stored in SRID 4326 (WGS84)
- **HeatmapPolyline** geometry is stored in SRID 3857 (Web Mercator), input in 4326 is transformed via `ST_Transform`
- MVT tiles are generated with `ST_AsMVT` / `ST_AsMVTGeom` and served as `application/x-protobuf`

## License

Proprietary
