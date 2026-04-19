<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on investment_offer investor and opportunity pair';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX unique_investment_offer_investor_opportunity ON investment_offer (investor_id, opportunity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX unique_investment_offer_investor_opportunity ON investment_offer');
    }
}