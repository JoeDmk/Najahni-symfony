<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add investment contracts and negotiation messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE investment_contract (id INT AUTO_INCREMENT NOT NULL, offer_id INT NOT NULL, investor_id INT NOT NULL, entrepreneur_id INT NOT NULL, title VARCHAR(255) NOT NULL, terms LONGTEXT NOT NULL, equity_percentage NUMERIC(5, 2) DEFAULT NULL, consideration LONGTEXT DEFAULT NULL, milestones LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, terms_digest VARCHAR(64) NOT NULL, investor_signature_name VARCHAR(150) DEFAULT NULL, investor_signature_hash VARCHAR(64) DEFAULT NULL, investor_signed_at DATETIME DEFAULT NULL, entrepreneur_signature_name VARCHAR(150) DEFAULT NULL, entrepreneur_signature_hash VARCHAR(64) DEFAULT NULL, entrepreneur_signed_at DATETIME DEFAULT NULL, last_message_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_35DFBDF953C674EE (offer_id), INDEX IDX_35DFBDF99AE528DA (investor_id), INDEX IDX_35DFBDF9176C89B5 (entrepreneur_id), INDEX IDX_35DFBDF9BF7B5801 (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE investment_contract_message (id INT AUTO_INCREMENT NOT NULL, contract_id INT NOT NULL, sender_id INT NOT NULL, body LONGTEXT NOT NULL, system_message TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_CDA5F0942576E0FD (contract_id), INDEX IDX_CDA5F094F624B39D (sender_id), INDEX IDX_CDA5F0945F37A13B (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE investment_contract ADD CONSTRAINT FK_35DFBDF953C674EE FOREIGN KEY (offer_id) REFERENCES investment_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_contract ADD CONSTRAINT FK_35DFBDF99AE528DA FOREIGN KEY (investor_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_contract ADD CONSTRAINT FK_35DFBDF9176C89B5 FOREIGN KEY (entrepreneur_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_contract_message ADD CONSTRAINT FK_CDA5F0942576E0FD FOREIGN KEY (contract_id) REFERENCES investment_contract (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_contract_message ADD CONSTRAINT FK_CDA5F094F624B39D FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investment_contract_message DROP FOREIGN KEY FK_CDA5F0942576E0FD');
        $this->addSql('ALTER TABLE investment_contract_message DROP FOREIGN KEY FK_CDA5F094F624B39D');
        $this->addSql('ALTER TABLE investment_contract DROP FOREIGN KEY FK_35DFBDF953C674EE');
        $this->addSql('ALTER TABLE investment_contract DROP FOREIGN KEY FK_35DFBDF99AE528DA');
        $this->addSql('ALTER TABLE investment_contract DROP FOREIGN KEY FK_35DFBDF9176C89B5');
        $this->addSql('DROP TABLE investment_contract_message');
        $this->addSql('DROP TABLE investment_contract');
    }
}