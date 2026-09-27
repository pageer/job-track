<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927000010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link contacts to the interview they were derived from.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD interview_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD INDEX IDX_CONTACT_INTERVIEW (interview_id)');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_CONTACT_INTERVIEW FOREIGN KEY (interview_id) REFERENCES interview (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_CONTACT_INTERVIEW');
        $this->addSql('ALTER TABLE contact DROP INDEX IDX_CONTACT_INTERVIEW');
        $this->addSql('ALTER TABLE contact DROP interview_id');
    }
}