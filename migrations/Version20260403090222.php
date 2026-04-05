<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403090222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Signalement : date et motif de clôture, utilisateur ayant clos.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report ADD closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD closure_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD closed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784E1FA7797 FOREIGN KEY (closed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_C42F7784E1FA7797 ON report (closed_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784E1FA7797');
        $this->addSql('DROP INDEX IDX_C42F7784E1FA7797');
        $this->addSql('ALTER TABLE report DROP closed_at');
        $this->addSql('ALTER TABLE report DROP closure_reason');
        $this->addSql('ALTER TABLE report DROP closed_by_id');
    }
}
