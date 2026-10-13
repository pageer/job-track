<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow application cover letters to be a link instead of inline text.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application ADD cover_letter_kind ENUM(\'text\', \'link\') DEFAULT NULL, ADD cover_letter_link_url VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application DROP cover_letter_kind, DROP cover_letter_link_url');
    }
}
