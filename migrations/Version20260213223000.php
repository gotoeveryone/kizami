<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add visibility flag to clients.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE clients ADD is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients DROP is_visible');
    }
}
