<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420132731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cartline ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cartline ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cartline ADD CONSTRAINT FK_AC6FBE4FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cartline ADD CONSTRAINT FK_AC6FBE4F4584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_AC6FBE4FA76ED395 ON cartline (user_id)');
        $this->addSql('CREATE INDEX IDX_AC6FBE4F4584665A ON cartline (product_id)');
        $this->addSql('ALTER TABLE "order" ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_F5299398A76ED395 ON "order" (user_id)');
        $this->addSql('ALTER TABLE order_line ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_line ADD order_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE14584665A FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_9CE58EE18D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_9CE58EE14584665A ON order_line (product_id)');
        $this->addSql('CREATE INDEX IDX_9CE58EE18D9F6D38 ON order_line (order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cartline DROP CONSTRAINT FK_AC6FBE4FA76ED395');
        $this->addSql('ALTER TABLE cartline DROP CONSTRAINT FK_AC6FBE4F4584665A');
        $this->addSql('DROP INDEX IDX_AC6FBE4FA76ED395');
        $this->addSql('DROP INDEX IDX_AC6FBE4F4584665A');
        $this->addSql('ALTER TABLE cartline DROP user_id');
        $this->addSql('ALTER TABLE cartline DROP product_id');
        $this->addSql('ALTER TABLE "order" DROP CONSTRAINT FK_F5299398A76ED395');
        $this->addSql('DROP INDEX IDX_F5299398A76ED395');
        $this->addSql('ALTER TABLE "order" DROP user_id');
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT FK_9CE58EE14584665A');
        $this->addSql('ALTER TABLE order_line DROP CONSTRAINT FK_9CE58EE18D9F6D38');
        $this->addSql('DROP INDEX IDX_9CE58EE14584665A');
        $this->addSql('DROP INDEX IDX_9CE58EE18D9F6D38');
        $this->addSql('ALTER TABLE order_line DROP product_id');
        $this->addSql('ALTER TABLE order_line DROP order_id');
    }
}
