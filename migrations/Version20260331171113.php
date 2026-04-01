<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds context, summary, and metadata to report (schema already created by earlier migrations).
 */
final class Version20260331171113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add context, summary, metadata to report';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ADD context TEXT DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE report ADD summary TEXT DEFAULT \'\' NOT NULL');
        $this->addSql("ALTER TABLE report ADD metadata JSONB DEFAULT '{}'::jsonb NOT NULL");
        $this->addSql('ALTER TABLE report ALTER COLUMN context DROP DEFAULT');
        $this->addSql('ALTER TABLE report ALTER COLUMN summary DROP DEFAULT');
        $this->addSql('ALTER TABLE report ALTER COLUMN metadata DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP context');
        $this->addSql('ALTER TABLE report DROP summary');
        $this->addSql('ALTER TABLE report DROP metadata');
    }
}
