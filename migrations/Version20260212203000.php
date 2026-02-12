<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create clients, work_categories, and time_entries tables with seed data for work categories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE clients (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created DATETIME NOT NULL,
    modified DATETIME NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE work_categories (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created DATETIME NOT NULL,
    modified DATETIME NOT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE time_entries (
    id INT AUTO_INCREMENT NOT NULL,
    client_id INT NOT NULL,
    work_category_id INT NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    hours DECIMAL(5, 2) NOT NULL,
    comment LONGTEXT DEFAULT NULL,
    created DATETIME NOT NULL,
    modified DATETIME NOT NULL,
    INDEX IDX_TIME_ENTRIES_CLIENT_ID (client_id),
    INDEX IDX_TIME_ENTRIES_WORK_CATEGORY_ID (work_category_id),
    INDEX IDX_TIME_ENTRIES_DATE (date),
    PRIMARY KEY(id),
    CONSTRAINT FK_TIME_ENTRIES_CLIENT_ID FOREIGN KEY (client_id) REFERENCES clients (id),
    CONSTRAINT FK_TIME_ENTRIES_WORK_CATEGORY_ID FOREIGN KEY (work_category_id) REFERENCES work_categories (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO work_categories (name, sort_order, created, modified) VALUES
('設計作業', 0, NOW(), NOW()),
('開発作業', 1, NOW(), NOW()),
('コーディング', 2, NOW(), NOW()),
('インフラ', 3, NOW(), NOW()),
('打ち合わせ', 4, NOW(), NOW()),
('その他', 5, NOW(), NOW())
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE time_entries');
        $this->addSql('DROP TABLE work_categories');
        $this->addSql('DROP TABLE clients');
    }
}
