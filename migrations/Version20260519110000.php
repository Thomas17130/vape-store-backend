<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add seen_count to product for popularity ranking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD seen_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product DROP seen_count');
    }
}
