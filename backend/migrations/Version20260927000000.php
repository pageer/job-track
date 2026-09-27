<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add networking tables (company, person, contact) and link jobs to companies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_COMPANY_USER (user_id), UNIQUE INDEX UNIQ_COMPANY_USER_NAME (user_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(254) DEFAULT NULL, linked_in_url VARCHAR(2048) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, company_id INT DEFAULT NULL, INDEX IDX_PERSON_USER (user_id), INDEX IDX_PERSON_COMPANY (company_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, description LONGTEXT DEFAULT NULL, needs_follow_up TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, person_id INT NOT NULL, INDEX IDX_CONTACT_PERSON (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE job ADD company_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE job ADD INDEX IDX_JOB_COMPANY (company_id)');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_COMPANY_USER FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_PERSON_USER FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_PERSON_COMPANY FOREIGN KEY (company_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_CONTACT_PERSON FOREIGN KEY (person_id) REFERENCES person (id)');
        $this->addSql('ALTER TABLE job ADD CONSTRAINT FK_JOB_COMPANY FOREIGN KEY (company_id) REFERENCES company (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job DROP FOREIGN KEY FK_JOB_COMPANY');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_CONTACT_PERSON');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_PERSON_COMPANY');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_PERSON_USER');
        $this->addSql('ALTER TABLE company DROP FOREIGN KEY FK_COMPANY_USER');
        $this->addSql('DROP INDEX IDX_JOB_COMPANY ON job');
        $this->addSql('ALTER TABLE job DROP company_id');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE company');
    }
}