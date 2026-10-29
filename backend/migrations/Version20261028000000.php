<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-user application settings, starting with OneDrive folders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_settings (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, one_drive_folders JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_USER_SETTINGS_USER (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_settings ADD CONSTRAINT FK_USER_SETTINGS_USER FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_settings DROP FOREIGN KEY FK_USER_SETTINGS_USER');
        $this->addSql('DROP TABLE user_settings');
    }
}
