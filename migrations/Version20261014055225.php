<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014055225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE answer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, given_answer VARCHAR(255) NOT NULL, correct BOOLEAN NOT NULL, answered_at DATETIME NOT NULL, user_id INTEGER NOT NULL, question_id INTEGER NOT NULL, session_id INTEGER NOT NULL, CONSTRAINT FK_DADD4A25A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_DADD4A251E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_DADD4A25613FECDF FOREIGN KEY (session_id) REFERENCES quiz_session (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_DADD4A256CD47AE1 ON answer (answered_at)');
        $this->addSql('CREATE INDEX IDX_DADD4A25A76ED395 ON answer (user_id)');
        $this->addSql('CREATE INDEX IDX_DADD4A251E27F6BF ON answer (question_id)');
        $this->addSql('CREATE INDEX IDX_DADD4A25613FECDF ON answer (session_id)');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(30) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE TABLE question (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, external_id VARCHAR(50) NOT NULL, level SMALLINT NOT NULL, type VARCHAR(10) NOT NULL, prompt CLOB NOT NULL, options CLOB NOT NULL, answers CLOB NOT NULL, explanation_cs CLOB NOT NULL, explanation_en CLOB NOT NULL, active BOOLEAN NOT NULL, topic_id INTEGER NOT NULL, CONSTRAINT FK_B6F7494E1F55203D FOREIGN KEY (topic_id) REFERENCES topic (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6F7494E9F75D7B0 ON question (external_id)');
        $this->addSql('CREATE INDEX IDX_B6F7494E9AEACC134B1EFC02 ON question (level, active)');
        $this->addSql('CREATE INDEX IDX_B6F7494E1F55203D ON question (topic_id)');
        $this->addSql('CREATE TABLE quiz_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, level SMALLINT DEFAULT NULL, mode VARCHAR(10) NOT NULL, question_ids CLOB NOT NULL, position INTEGER NOT NULL, correct_count INTEGER NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, topic_id INTEGER DEFAULT NULL, CONSTRAINT FK_C21E7874A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C21E78741F55203D FOREIGN KEY (topic_id) REFERENCES topic (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C21E7874A76ED395 ON quiz_session (user_id)');
        $this->addSql('CREATE INDEX IDX_C21E78741F55203D ON quiz_session (topic_id)');
        $this->addSql('CREATE TABLE topic (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(50) NOT NULL, title_cs VARCHAR(150) NOT NULL, title_en VARCHAR(150) NOT NULL, position INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9D40DE1B77153098 ON topic (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE answer');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE quiz_session');
        $this->addSql('DROP TABLE topic');
    }
}
