<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240715190805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__event AS SELECT id, location_id, title, link, description, start_at, end_at, organizer, image, slug, uuid, published, source FROM event');
        $this->addSql('DROP TABLE event');
        $this->addSql('CREATE TABLE event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, location_id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, link VARCHAR(255) NOT NULL, description CLOB NOT NULL, start_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , end_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , organizer VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, uuid BLOB NOT NULL --(DC2Type:uuid)
        , published DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , source VARCHAR(255) NOT NULL, CONSTRAINT FK_3BAE0AA764D218E FOREIGN KEY (location_id) REFERENCES postal_address (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO event (id, location_id, title, link, description, start_at, end_at, organizer, image, slug, uuid, published, source) SELECT id, location_id, title, link, description, start_at, end_at, organizer, image, slug, uuid, published, source FROM __temp__event');
        $this->addSql('DROP TABLE __temp__event');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA7D17F50A6 ON event (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA7989D9B62 ON event (slug)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA764D218E ON event (location_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__event AS SELECT id, location_id, uuid, title, link, description, start_at, end_at, organizer, image, slug, published, source FROM event');
        $this->addSql('DROP TABLE event');
        $this->addSql('CREATE TABLE event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, location_id INTEGER DEFAULT NULL, uuid BLOB NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) NOT NULL, link VARCHAR(255) NOT NULL, description CLOB NOT NULL, start_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , end_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , organizer VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, published DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , source VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_3BAE0AA764D218E FOREIGN KEY (location_id) REFERENCES postal_address (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO event (id, location_id, uuid, title, link, description, start_at, end_at, organizer, image, slug, published, source) SELECT id, location_id, uuid, title, link, description, start_at, end_at, organizer, image, slug, published, source FROM __temp__event');
        $this->addSql('DROP TABLE __temp__event');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA7D17F50A6 ON event (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA7989D9B62 ON event (slug)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA764D218E ON event (location_id)');
    }
}
