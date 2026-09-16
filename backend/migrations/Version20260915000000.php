<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Storage for per-user OneDrive OAuth tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE one_drive_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, access_token LONGTEXT NOT NULL, refresh_token LONGTEXT NOT NULL, expires_at DATETIME NOT NULL, account_email VARCHAR(255) DEFAULT NULL, account_display_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_ONEDRIVE_TOKEN_USER (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE one_drive_token ADD CONSTRAINT FK_ONE_DRIVE_TOKEN_USER FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE one_drive_token DROP FOREIGN KEY FK_ONE_DRIVE_TOKEN_USER');
        $this->addSql('DROP TABLE one_drive_token');
    }
}