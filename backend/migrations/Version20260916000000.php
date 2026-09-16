<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add job_note table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_note (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, job_id INT NOT NULL, INDEX IDX_4A9B2C6DBE04EA9 (job_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE job_note ADD CONSTRAINT FK_4A9B2C6DBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_note DROP FOREIGN KEY FK_4A9B2C6DBE04EA9');
        $this->addSql('DROP TABLE job_note');
    }
}