<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260923133659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the job_activity table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_activity (id INT AUTO_INCREMENT NOT NULL, description VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, date DATE NOT NULL, hours_spent DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_CB33AC21A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE job_activity ADD CONSTRAINT FK_CB33AC21A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_activity DROP FOREIGN KEY FK_CB33AC21A76ED395');
        $this->addSql('DROP TABLE job_activity');
    }
}
