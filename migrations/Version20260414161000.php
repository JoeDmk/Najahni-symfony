<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add investment module follow-up schema: profiles, milestones, risk fields and contract signature images';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investment_opportunity ADD risk_score DOUBLE PRECISION DEFAULT NULL, ADD risk_label VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE investment_offer ADD risk_acknowledged TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE investment_contract ADD investor_signature_image LONGTEXT DEFAULT NULL, ADD entrepreneur_signature_image LONGTEXT DEFAULT NULL');

        $this->addSql('CREATE TABLE investor_profile (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, preferred_sectors VARCHAR(500) DEFAULT NULL, risk_tolerance INT NOT NULL DEFAULT 5, budget_min NUMERIC(15, 2) NOT NULL DEFAULT 0, budget_max NUMERIC(15, 2) NOT NULL DEFAULT 10000000, horizon_months INT NOT NULL DEFAULT 12, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7BE31931A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE investor_profile ADD CONSTRAINT FK_7BE31931A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE contract_milestone (id INT AUTO_INCREMENT NOT NULL, contract_id INT NOT NULL, label VARCHAR(255) NOT NULL, percentage NUMERIC(5, 2) NOT NULL, amount NUMERIC(15, 2) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT \'PENDING\', position INT NOT NULL, payment_intent_id VARCHAR(255) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, released_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_5325C3C2576E0FD (contract_id), INDEX IDX_5325C3C25F37A13B (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contract_milestone ADD CONSTRAINT FK_5325C3C2576E0FD FOREIGN KEY (contract_id) REFERENCES investment_contract (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract_milestone DROP FOREIGN KEY FK_5325C3C2576E0FD');
        $this->addSql('DROP TABLE contract_milestone');

        $this->addSql('ALTER TABLE investor_profile DROP FOREIGN KEY FK_7BE31931A76ED395');
        $this->addSql('DROP TABLE investor_profile');

        $this->addSql('ALTER TABLE investment_contract DROP investor_signature_image, DROP entrepreneur_signature_image');
        $this->addSql('ALTER TABLE investment_offer DROP risk_acknowledged');
        $this->addSql('ALTER TABLE investment_opportunity DROP risk_score, DROP risk_label');
    }
}
