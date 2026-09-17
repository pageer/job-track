<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260917000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add no_response value to the job status enum.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job MODIFY status ENUM('investigating', 'applied', 'in_progress', 'no_response', 'rejected', 'accepted') NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job MODIFY status ENUM('investigating', 'applied', 'in_progress', 'rejected', 'accepted') NOT NULL");
    }
}