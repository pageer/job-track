<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810012210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE application (id INT AUTO_INCREMENT NOT NULL, resume_kind ENUM(\'file\', \'link\') DEFAULT NULL, resume_file_name VARCHAR(255) DEFAULT NULL, resume_file_path VARCHAR(255) DEFAULT NULL, resume_mime_type VARCHAR(120) DEFAULT NULL, resume_file_size INT DEFAULT NULL, resume_link_url VARCHAR(2048) DEFAULT NULL, cover_letter_html LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, job_id INT NOT NULL, UNIQUE INDEX UNIQ_A45BDDC1BE04EA9 (job_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cover_letter (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_EBE6B47A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE interview (id INT AUTO_INCREMENT NOT NULL, date DATETIME NOT NULL, interviewers JSON NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, application_id INT NOT NULL, INDEX IDX_CF1D3C343E030ACD (application_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, company VARCHAR(255) NOT NULL, description_html LONGTEXT DEFAULT NULL, description_url VARCHAR(2048) DEFAULT NULL, status ENUM(\'investigating\', \'applied\', \'in_progress\', \'rejected\', \'accepted\') NOT NULL, created_at DATETIME NOT NULL, job_search_id INT NOT NULL, INDEX IDX_FBD8E0F8A2B78FB8 (job_search_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job_search (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_E4F4F626A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resume (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, kind ENUM(\'file\', \'link\') NOT NULL, file_name VARCHAR(255) DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(120) DEFAULT NULL, file_size INT DEFAULT NULL, link_url VARCHAR(2048) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_60C1D0A0A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC1BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');
        $this->addSql('ALTER TABLE cover_letter ADD CONSTRAINT FK_EBE6B47A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT FK_CF1D3C343E030ACD FOREIGN KEY (application_id) REFERENCES application (id)');
        $this->addSql('ALTER TABLE job ADD CONSTRAINT FK_FBD8E0F8A2B78FB8 FOREIGN KEY (job_search_id) REFERENCES job_search (id)');
        $this->addSql('ALTER TABLE job_search ADD CONSTRAINT FK_E4F4F626A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE resume ADD CONSTRAINT FK_60C1D0A0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC1BE04EA9');
        $this->addSql('ALTER TABLE cover_letter DROP FOREIGN KEY FK_EBE6B47A76ED395');
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY FK_CF1D3C343E030ACD');
        $this->addSql('ALTER TABLE job DROP FOREIGN KEY FK_FBD8E0F8A2B78FB8');
        $this->addSql('ALTER TABLE job_search DROP FOREIGN KEY FK_E4F4F626A76ED395');
        $this->addSql('ALTER TABLE resume DROP FOREIGN KEY FK_60C1D0A0A76ED395');
        $this->addSql('DROP TABLE application');
        $this->addSql('DROP TABLE cover_letter');
        $this->addSql('DROP TABLE interview');
        $this->addSql('DROP TABLE job');
        $this->addSql('DROP TABLE job_search');
        $this->addSql('DROP TABLE resume');
        $this->addSql('DROP TABLE user');
    }
}
