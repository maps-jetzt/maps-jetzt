<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209135916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE IF EXISTS activity_id_seq CASCADE');
        $this->addSql('DROP TABLE IF EXISTS activity');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE activity_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE activity (id INT DEFAULT nextval(\'activity_id_seq\'::regclass) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, geom geometry(LINESTRING, 4326) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
    }
}
