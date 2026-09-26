<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the todo table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE todo (id INT AUTO_INCREMENT NOT NULL, description VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, target_date DATE NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_A9B2C3D4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE todo ADD CONSTRAINT FK_A9B2C3D4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE todo DROP FOREIGN KEY FK_A9B2C3D4A76ED395');
        $this->addSql('DROP TABLE todo');
    }
}