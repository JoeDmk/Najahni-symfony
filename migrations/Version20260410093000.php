<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification action link fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD action_url VARCHAR(500) DEFAULT NULL, ADD action_label VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP action_url, DROP action_label');
    }
}