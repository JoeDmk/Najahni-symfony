<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430211000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing face_encoding column to user table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD COLUMN IF NOT EXISTS face_encoding LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP COLUMN IF EXISTS face_encoding');
    }
}