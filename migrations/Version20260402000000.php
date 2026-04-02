<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne la table report sur le modèle métier : type de situation, résumé, contextes aggravant/attenuant, date d’incident, crédibilité.
 */
final class Version20260402000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Report: situation_type, situation_summary, aggravating/mitigating context, incident_date, report_credibility';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report RENAME COLUMN category TO situation_type');
        $this->addSql('ALTER TABLE report RENAME COLUMN summary TO situation_summary');
        $this->addSql('ALTER TABLE report ADD aggravating_context TEXT DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE report ADD mitigating_context TEXT DEFAULT \'\' NOT NULL');
        $this->addSql('UPDATE report SET mitigating_context = context');
        $this->addSql('ALTER TABLE report DROP context');
        $this->addSql('ALTER TABLE report ALTER COLUMN aggravating_context DROP DEFAULT');
        $this->addSql('ALTER TABLE report ALTER COLUMN mitigating_context DROP DEFAULT');
        $this->addSql('ALTER TABLE report ADD incident_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE report SET incident_date = report_date');
        $this->addSql('ALTER TABLE report ALTER COLUMN incident_date SET NOT NULL');
        $this->addSql('ALTER TABLE report ALTER COLUMN incident_date DROP DEFAULT');
        $this->addSql("ALTER TABLE report ADD report_credibility VARCHAR(32) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE report ALTER COLUMN report_credibility DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP report_credibility');
        $this->addSql('ALTER TABLE report DROP incident_date');
        $this->addSql('ALTER TABLE report ADD context TEXT DEFAULT \'\' NOT NULL');
        $this->addSql('UPDATE report SET context = mitigating_context');
        $this->addSql('ALTER TABLE report ALTER COLUMN context DROP DEFAULT');
        $this->addSql('ALTER TABLE report DROP mitigating_context');
        $this->addSql('ALTER TABLE report DROP aggravating_context');
        $this->addSql('ALTER TABLE report RENAME COLUMN situation_summary TO summary');
        $this->addSql('ALTER TABLE report RENAME COLUMN situation_type TO category');
    }
}
