<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261126000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the accomplishment table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accomplishment (id INT AUTO_INCREMENT NOT NULL, `date` DATE NOT NULL, company VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, user_id INT NOT NULL, INDEX IDX_ACCOMPLISHMENT_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE accomplishment ADD CONSTRAINT FK_ACCOMPLISHMENT_USER FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accomplishment DROP FOREIGN KEY FK_ACCOMPLISHMENT_USER');
        $this->addSql('DROP TABLE accomplishment');
    }
}
