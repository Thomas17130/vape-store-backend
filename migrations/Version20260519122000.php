<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role and auth_token to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD role VARCHAR(50) NOT NULL DEFAULT 'ROLE_USER', ADD auth_token VARCHAR(80) DEFAULT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64989D9B62 ON `user` (auth_token)');
        $this->addSql("UPDATE `user` SET role = 'ROLE_USER' WHERE role IS NULL OR role = ''");
        $this->addSql("UPDATE `user` SET role = 'ROLE_ADMIN' WHERE id = (SELECT id FROM (SELECT id FROM `user` ORDER BY id ASC LIMIT 1) AS first_user)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D64989D9B62 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP role, DROP auth_token');
    }
}
