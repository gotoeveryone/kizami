<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused API keys table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_keys');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT NOT NULL,
    key_hash VARCHAR(64) NOT NULL,
    label VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created DATETIME NOT NULL,
    modified DATETIME NOT NULL,
    UNIQUE INDEX UNIQ_API_KEYS_HASH (key_hash),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }
}
