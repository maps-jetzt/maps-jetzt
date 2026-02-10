<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210103612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user table and heatmap.user_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "user" (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            api_key VARCHAR(64) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C912ED9D ON "user" (api_key)');

        $this->addSql('ALTER TABLE heatmap ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE heatmap ADD CONSTRAINT FK_4D22F8D8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4D22F8D8A76ED395 ON heatmap (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE heatmap DROP CONSTRAINT FK_4D22F8D8A76ED395');
        $this->addSql('DROP INDEX IDX_4D22F8D8A76ED395');
        $this->addSql('ALTER TABLE heatmap DROP user_id');
        $this->addSql('DROP TABLE IF EXISTS "user"');
    }
}
