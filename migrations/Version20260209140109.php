<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209140109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add viewport fields (center_lon, center_lat, zoom) to heatmap';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE heatmap ADD center_lon DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE heatmap ADD center_lat DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE heatmap ADD zoom INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE heatmap DROP center_lon');
        $this->addSql('ALTER TABLE heatmap DROP center_lat');
        $this->addSql('ALTER TABLE heatmap DROP zoom');
    }
}
