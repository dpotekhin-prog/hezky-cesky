<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014071546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parent-child links and pairing codes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parent_child (child_id INTEGER NOT NULL, parent_id INTEGER NOT NULL, PRIMARY KEY (child_id, parent_id), CONSTRAINT FK_EE82C08ADD62C21B FOREIGN KEY (child_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EE82C08A727ACA70 FOREIGN KEY (parent_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EE82C08ADD62C21B ON parent_child (child_id)');
        $this->addSql('CREATE INDEX IDX_EE82C08A727ACA70 ON parent_child (parent_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, username, roles, password, created_at FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(30) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, pairing_code VARCHAR(12) DEFAULT NULL)');
        $this->addSql('INSERT INTO app_user (id, username, roles, password, created_at) SELECT id, username, roles, password, created_at FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        // Existing accounts get a pairing code too (8 hex chars, unique enough for a family app).
        $this->addSql('UPDATE app_user SET pairing_code = upper(hex(randomblob(4)))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9D0ACCEDE ON app_user (pairing_code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE parent_child');
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, username, roles, password, created_at FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(30) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO app_user (id, username, roles, password, created_at) SELECT id, username, roles, password, created_at FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
    }
}
