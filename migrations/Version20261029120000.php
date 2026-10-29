<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add organizer url, performer and price to event for JSON-LD';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD COLUMN organizer_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD COLUMN performer VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD COLUMN price DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP COLUMN organizer_url');
        $this->addSql('ALTER TABLE event DROP COLUMN performer');
        $this->addSql('ALTER TABLE event DROP COLUMN price');
    }
}
