<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241115080938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deal (id INT AUTO_INCREMENT NOT NULL, order_id VARCHAR(255) DEFAULT NULL, lead_id VARCHAR(255) DEFAULT NULL, client_id VARCHAR(255) DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, date_created DATETIME DEFAULT NULL, date_modified DATETIME DEFAULT NULL, contact_id VARCHAR(255) DEFAULT NULL, offer_id VARCHAR(255) DEFAULT NULL, is_sent TINYINT(1) DEFAULT NULL, json_value JSON DEFAULT NULL, bitrix_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_E3FEC1168D9F6D38 (order_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE deal_fields_b (id INT AUTO_INCREMENT NOT NULL, country VARCHAR(255) NOT NULL, value JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE deal');
        $this->addSql('DROP TABLE deal_fields_b');
    }
}
