<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create api_keys table for API key management';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_keys (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            last_connection DATETIME DEFAULT NULL,
            email_verified TINYINT(1) DEFAULT 0 NOT NULL,
            verification_token VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            key_value VARCHAR(255) DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL,
            usage_count INT DEFAULT 0 NOT NULL,
            UNIQUE INDEX UNIQ_9579321FE7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE api_keys');
    }
}

