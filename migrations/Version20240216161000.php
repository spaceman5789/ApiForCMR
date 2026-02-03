<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240216161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_key (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, is_active TINYINT(1) NOT NULL, token VARCHAR(255) NOT NULL, client_id VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_C912ED9DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE api_usage (id INT AUTO_INCREMENT NOT NULL, apikey_id INT DEFAULT NULL, used_at DATETIME NOT NULL, endpoint VARCHAR(255) NOT NULL, response_message LONGTEXT NOT NULL, INDEX IDX_F47E0AA0A3B9ED7F (apikey_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, offer_id VARCHAR(255) NOT NULL, country VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, user_id VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE api_key ADD CONSTRAINT FK_C912ED9DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE api_usage ADD CONSTRAINT FK_F47E0AA0A3B9ED7F FOREIGN KEY (apikey_id) REFERENCES api_key (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_key DROP FOREIGN KEY FK_C912ED9DA76ED395');
        $this->addSql('ALTER TABLE api_usage DROP FOREIGN KEY FK_F47E0AA0A3B9ED7F');
        $this->addSql('DROP TABLE api_key');
        $this->addSql('DROP TABLE api_usage');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE user');
    }
}
