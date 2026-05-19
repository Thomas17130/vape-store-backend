<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enterprise commerce schema: categories, product media, variants, inventory movement, wishlist, addresses, and store orders';
    }

    public function up(Schema $schema): void
    {
        // Legacy tables created in early migrations may not be InnoDB, which breaks FK creation on MariaDB.
        $this->addSql('ALTER TABLE brand ENGINE=InnoDB');
        $this->addSql('ALTER TABLE cartline ENGINE=InnoDB');
        $this->addSql('ALTER TABLE `order` ENGINE=InnoDB');
        $this->addSql('ALTER TABLE order_line ENGINE=InnoDB');
        $this->addSql('ALTER TABLE product ENGINE=InnoDB');
        $this->addSql('ALTER TABLE `user` ENGINE=InnoDB');

        $this->addSql('ALTER TABLE product ADD sku VARCHAR(100) DEFAULT NULL, ADD slug VARCHAR(255) DEFAULT NULL, ADD sale_price INT DEFAULT NULL, ADD is_active TINYINT(1) NOT NULL DEFAULT 1, ADD created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", ADD updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)"');
        $this->addSql('UPDATE product SET sku = CONCAT("SKU-", id), slug = CONCAT("product-", id), created_at = NOW(), updated_at = NOW() WHERE sku IS NULL OR slug IS NULL OR created_at IS NULL OR updated_at IS NULL');
        $this->addSql('ALTER TABLE product MODIFY sku VARCHAR(100) NOT NULL, MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04ADEFCC694 ON product (sku)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D34A04A989D9B62 ON product (slug)');

        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(150) NOT NULL, slug VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), INDEX IDX_64C19C1727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id)');

        $this->addSql('CREATE TABLE product_category (product_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_3A6D3B794584665A (product_id), INDEX IDX_3A6D3B7912469DE2 (category_id), PRIMARY KEY(product_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_3A6D3B794584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_3A6D3B7912469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE product_image (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, url VARCHAR(500) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, position INT NOT NULL, is_primary TINYINT(1) NOT NULL, INDEX IDX_64617F034584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, sku VARCHAR(100) NOT NULL, title VARCHAR(255) NOT NULL, attributes JSON DEFAULT NULL, price INT NOT NULL, quantity INT NOT NULL, is_default TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_8A7F870F8D9F6D38 (sku), INDEX IDX_8A7F870F4584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_8A7F870F4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE inventory_movement (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, variant_id INT DEFAULT NULL, quantity_change INT NOT NULL, reason VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_2D02F0AF4584665A (product_id), INDEX IDX_2D02F0AF3B69A9AF (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_2D02F0AF4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_2D02F0AF3B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE customer_address (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, label VARCHAR(120) NOT NULL, recipient_name VARCHAR(200) NOT NULL, phone VARCHAR(50) NOT NULL, line1 VARCHAR(255) NOT NULL, line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(120) NOT NULL, postal_code VARCHAR(20) NOT NULL, country VARCHAR(120) NOT NULL, is_default TINYINT(1) NOT NULL, INDEX IDX_A166A5FA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE customer_address ADD CONSTRAINT FK_A166A5FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE store_order (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, order_number VARCHAR(60) NOT NULL, status VARCHAR(40) NOT NULL, subtotal INT NOT NULL, shipping_cost INT NOT NULL, total INT NOT NULL, shipping_snapshot JSON NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_8656E3E9D97ED6AB (order_number), INDEX IDX_8656E3E9A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE store_order ADD CONSTRAINT FK_8656E3E9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE store_order_item (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, product_id INT NOT NULL, variant_id INT DEFAULT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, total_price INT NOT NULL, INDEX IDX_508E70C88D9F6D38 (order_id), INDEX IDX_508E70C84584665A (product_id), INDEX IDX_508E70C83B69A9AF (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE store_order_item ADD CONSTRAINT FK_508E70C88D9F6D38 FOREIGN KEY (order_id) REFERENCES store_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE store_order_item ADD CONSTRAINT FK_508E70C84584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE store_order_item ADD CONSTRAINT FK_508E70C83B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE wishlist_item (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, product_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX uniq_user_product_wishlist (user_id, product_id), INDEX IDX_5A6A1B20A76ED395 (user_id), INDEX IDX_5A6A1B204584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wishlist_item ADD CONSTRAINT FK_5A6A1B20A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wishlist_item ADD CONSTRAINT FK_5A6A1B204584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wishlist_item DROP FOREIGN KEY FK_5A6A1B20A76ED395');
        $this->addSql('ALTER TABLE wishlist_item DROP FOREIGN KEY FK_5A6A1B204584665A');
        $this->addSql('ALTER TABLE store_order_item DROP FOREIGN KEY FK_508E70C88D9F6D38');
        $this->addSql('ALTER TABLE store_order_item DROP FOREIGN KEY FK_508E70C84584665A');
        $this->addSql('ALTER TABLE store_order_item DROP FOREIGN KEY FK_508E70C83B69A9AF');
        $this->addSql('ALTER TABLE store_order DROP FOREIGN KEY FK_8656E3E9A76ED395');
        $this->addSql('ALTER TABLE customer_address DROP FOREIGN KEY FK_A166A5FA76ED395');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_2D02F0AF4584665A');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_2D02F0AF3B69A9AF');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_8A7F870F4584665A');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_3A6D3B794584665A');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_3A6D3B7912469DE2');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');

        $this->addSql('DROP TABLE wishlist_item');
        $this->addSql('DROP TABLE store_order_item');
        $this->addSql('DROP TABLE store_order');
        $this->addSql('DROP TABLE customer_address');
        $this->addSql('DROP TABLE inventory_movement');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE category');

        $this->addSql('DROP INDEX UNIQ_D34A04ADEFCC694 ON product');
        $this->addSql('DROP INDEX UNIQ_D34A04A989D9B62 ON product');
        $this->addSql('ALTER TABLE product DROP COLUMN sku, DROP COLUMN slug, DROP COLUMN sale_price, DROP COLUMN is_active, DROP COLUMN created_at, DROP COLUMN updated_at');
    }
}
