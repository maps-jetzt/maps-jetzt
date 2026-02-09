<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209134625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hash and timestamps to entities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()');
        $this->addSql('ALTER TABLE activity ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE heatmap ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE heatmap_polyline ADD hash VARCHAR(32) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE heatmap_polyline ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()');
        $this->addSql('UPDATE heatmap_polyline SET hash = md5(ST_AsText(geom))');
        $this->addSql('ALTER TABLE heatmap_polyline ALTER hash DROP DEFAULT');
        $this->addSql('ALTER TABLE heatmap_polyline ALTER created_at DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6AC07E70D12F42C3D1B862B8 ON heatmap_polyline (heatmap_id, hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP created_at');
        $this->addSql('ALTER TABLE activity DROP updated_at');
        $this->addSql('ALTER TABLE heatmap DROP updated_at');
        $this->addSql('DROP INDEX UNIQ_6AC07E70D12F42C3D1B862B8');
        $this->addSql('ALTER TABLE heatmap_polyline DROP hash');
        $this->addSql('ALTER TABLE heatmap_polyline DROP created_at');
    }
}
