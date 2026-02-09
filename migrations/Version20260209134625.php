<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209134625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: heatmap + heatmap_polyline';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE heatmap (
            id SERIAL PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            center_lon DOUBLE PRECISION DEFAULT NULL,
            center_lat DOUBLE PRECISION DEFAULT NULL,
            zoom INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4D22F8D8772E836A ON heatmap (identifier)');

        $this->addSql('CREATE TABLE heatmap_polyline (
            id SERIAL PRIMARY KEY,
            heatmap_id INT NOT NULL REFERENCES heatmap (id) ON DELETE CASCADE,
            hash VARCHAR(32) NOT NULL,
            geom geometry(LINESTRING, 3857) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6AC07E70D12F42C3D1B862B8 ON heatmap_polyline (heatmap_id, hash)');
        $this->addSql('CREATE INDEX heatmap_polyline_geom_idx ON heatmap_polyline USING GIST (geom)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS heatmap_polyline');
        $this->addSql('DROP TABLE IF EXISTS heatmap');
    }
}
