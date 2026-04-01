<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen report.description to TEXT for public feedback';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ALTER description TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ALTER description TYPE VARCHAR(255)');
    }
}
