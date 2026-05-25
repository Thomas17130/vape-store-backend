<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh token storage for JWT authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD refresh_token_hash VARCHAR(64) DEFAULT NULL, ADD refresh_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C8D3A12A ON `user` (refresh_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649C8D3A12A ON `user`');
        $this->addSql('ALTER TABLE `user` DROP refresh_token_hash, DROP refresh_token_expires_at');
    }
}
