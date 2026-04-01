<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout des colonnes analyse IA au report - VERSION RGPD
 * Aucune donnée personnelle (nom, prénom) n'est stockée ici.
 * Seul le matricule est utilisé comme identifiant anonymisé.
 */
final class Version20260401140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonnes IA + contexte externe au report (RGPD : matricule uniquement)';
    }

    public function up(Schema $schema): void
    {
        // Identifiant anonymisé RGPD (remplace nom/prénom)
        $this->addSql("ALTER TABLE report ADD matricule VARCHAR(50) DEFAULT '' NOT NULL");

        // Colonnes analyse IA
        $this->addSql("ALTER TABLE report ADD gravite VARCHAR(10) DEFAULT 'moyen' NOT NULL");
        $this->addSql('ALTER TABLE report ADD score_ia INT DEFAULT 0 NOT NULL');
        $this->addSql("ALTER TABLE report ADD type_fait VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE report ADD contexte_attenuant BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE report ADD raison_contexte TEXT DEFAULT '' NOT NULL");
        $this->addSql("ALTER TABLE report ADD recommandation_ia TEXT DEFAULT '' NOT NULL");

        // Colonnes contexte externe (météo, heure de pointe)
        $this->addSql("ALTER TABLE report ADD meteo_contexte VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE report ADD heure_pointe BOOLEAN DEFAULT false NOT NULL');

        // Courrier généré par IA
        $this->addSql("ALTER TABLE report ADD courrier_genere TEXT DEFAULT '' NOT NULL");

        // Statut du dossier
        $this->addSql("ALTER TABLE report ADD statut_traitement VARCHAR(20) DEFAULT 'en_attente' NOT NULL");

        // Nombre de signalements historique anonymisé
        $this->addSql('ALTER TABLE report ADD nb_signalements_historique INT DEFAULT 0 NOT NULL');

        // Contraintes de valeurs
        $this->addSql("ALTER TABLE report ADD CONSTRAINT chk_gravite CHECK (gravite IN ('faible', 'moyen', 'eleve'))");
        $this->addSql("ALTER TABLE report ADD CONSTRAINT chk_statut CHECK (statut_traitement IN ('en_attente', 'en_cours', 'traite', 'classe'))");

        // Index pour performances
        $this->addSql('CREATE INDEX IDX_REPORT_GRAVITE ON report (gravite)');
        $this->addSql('CREATE INDEX IDX_REPORT_STATUT ON report (statut_traitement)');
        $this->addSql('CREATE INDEX IDX_REPORT_MATRICULE ON report (matricule)');
        $this->addSql('CREATE INDEX IDX_REPORT_DRIVER_DATE ON report (driver_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_REPORT_GRAVITE');
        $this->addSql('DROP INDEX IDX_REPORT_STATUT');
        $this->addSql('DROP INDEX IDX_REPORT_MATRICULE');
        $this->addSql('DROP INDEX IDX_REPORT_DRIVER_DATE');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT chk_gravite');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT chk_statut');
        $this->addSql('ALTER TABLE report DROP matricule');
        $this->addSql('ALTER TABLE report DROP gravite');
        $this->addSql('ALTER TABLE report DROP score_ia');
        $this->addSql('ALTER TABLE report DROP type_fait');
        $this->addSql('ALTER TABLE report DROP contexte_attenuant');
        $this->addSql('ALTER TABLE report DROP raison_contexte');
        $this->addSql('ALTER TABLE report DROP recommandation_ia');
        $this->addSql('ALTER TABLE report DROP meteo_contexte');
        $this->addSql('ALTER TABLE report DROP heure_pointe');
        $this->addSql('ALTER TABLE report DROP courrier_genere');
        $this->addSql('ALTER TABLE report DROP statut_traitement');
        $this->addSql('ALTER TABLE report DROP nb_signalements_historique');
    }
}
