# Tableau de bord « manager » — fonctionnement et périmètre

Ce document décrit comment le tableau de bord de gestion est exposé dans l’application, ce qu’y voit un **manager**, quelles **actions** il peut entreprendre (directement ou via la navigation), et comment cela se compare aux autres profils.

## Accès et rôles

| Élément | Détail |
|--------|--------|
| **URL** | `/` (nom de route Symfony : `app_home`). |
| **Contrôleur** | `App\Controller\DashboardController::index`. |
| **Sécurité** | Toute l’application sous `/` exige au minimum `ROLE_USER`, sauf les routes explicitement publiques (login, formulaire voyageur, etc.) — voir `config/packages/security.yaml`. |
| **Compte de démo** | Utilisateur `manager` chargé par `ManagerUserFixtures` : rôles `ROLE_MANAGER` et `ROLE_USER`. Mot de passe indiqué dans la fixture (`ManagerFixture!2026`). |
| **Redirection après login** | `LoginFormAuthenticator` envoie les utilisateurs qui ont `ROLE_USER` vers `app_home` (sauf URL cible explicite en session). Les comptes **inspecteur** (`ROLE_INSPECTOR` sans `ROLE_USER`) sont renvoyés vers le formulaire de signalement public : ils **n’utilisent pas** ce tableau de bord. |

En résumé : le « tableau de bord manager » est en fait le **tableau de bord de toute personne connectée avec `ROLE_USER`** (manager, admin, ou utilisateur enregistré avec seulement `ROLE_USER`). Le libellé « manager » dans les fixtures reflète le cas d’usage principal ; **il n’y a pas, dans le code du dashboard, de filtrage des données selon `ROLE_MANAGER`**.

## Visibilité des données

Les indicateurs et graphiques sont calculés à partir de **l’ensemble des signalements et conducteurs en base** (pas de restriction par équipe, ligne ou manager).

Données et métriques typiques fournies à la vue `dashboard/index.html.twig` :

- **Compteurs globaux** : nombre total de signalements, signalements sur 7 / 30 / 90 jours, tendance sur 7 jours (comparée à la fenêtre précédente).
- **Conducteurs** : nombre total en base ; liste **« top conducteurs à risque »** (volume de signalements sur un an, limité à 6 entrées dans le contrôleur).
- **Taux de traitement** : proportion de signalements **clos** parmi le total historique (`countTreated` / total).
- **Bloc KPI par période** (7 jours, 30 jours, 90 jours, 1 an) : volume de signalements, nombre de signalements de **gravité élevée** (`high`), taux de clôture sur la période, et nombre de conducteurs avec **au moins 3 signalements** sur la période.
- **Liste paginée** des signalements récents pour la gestion (18 par page), ordonnée pour l’affichage opérationnel.
- **Graphiques / séries** (fenêtres temporelles variables : 30 jours, 8 semaines, 1 an, etc.) : évolution par jour, mois, semaine ; répartition par type de situation, gravité, année, source, heure, jour de la semaine ; top lignes de transport ; codes de lignes distincts pour filtres côté interface.

**Note** : un indicateur affiché comme part de « conducteurs ayant progressé » est calculé dans le contrôleur à partir d’une **formule fixe (42 % du nombre de conducteurs)** — il s’agit d’un **placeholder de démonstration**, pas d’une mesure issue des données métier.

## Actions possibles depuis l’interface « espace gestion »

La barre de navigation (`templates/layout/_header.html.twig`) pour un utilisateur avec `ROLE_USER` propose :

1. **Tableau de bord** — `/`
2. **Signalements** — liste filtrable (`app_report_index`) : période, gravité, type de situation, conducteur, bus
3. **Conducteurs** — liste et fiches (`DriverController`)
4. **Formulaire public** — création de signalement voyageur
5. **Déconnexion**

Sur le **tableau de bord** lui-même, l’usage principal est la **lecture** (KPI, graphiques, liens vers le détail des signalements). Les équipements de filtre dans la page servent à **explorer visuellement** les séries (comportement géré côté client dans le gabarit).

### Fiche signalement (`/report/{id}`)

La consultation du détail d’un signalement est ouverte à tout `ROLE_USER`. En revanche, **seuls `ROLE_MANAGER` et `ROLE_ADMIN`** peuvent :

- **Enregistrer une raison de clôture** sans clôturer (`POST` vers `app_report_save_closure_reason`) — brouillon ou mise à jour du texte (y compris vidage du champ).
- **Clôturer** le signalement (`POST` vers `app_report_close`) : date/heure de clôture, raison obligatoire (**minimum 5 caractères**), enregistrement de l’utilisateur auteur (`closedBy`). Un signalement déjà clos ne peut plus être modifié.

Ces règles sont appliquées dans `ReportController` ; le formulaire de clôture n’est passé au gabarit que lorsque l’utilisateur a le droit de clôturer et que le signalement est encore ouvert.

### Module Conducteurs

Les routes `DriverController` (liste, création, édition, suppression, fiche avec historique des signalements du conducteur) **ne vérifient pas** `ROLE_MANAGER` : tout utilisateur avec `ROLE_USER` peut théoriquement effectuer ces opérations. En production, le durcissement (responsabilité manager vs lecture seule) devrait reposer sur une politique métier ou des règles `access_control` / `IsGranted` si nécessaire.

## Comparaison rapide des profils

| Profil | Accès `/` (dashboard) | Clôture des signalements | Navigation complète gestion |
|--------|------------------------|---------------------------|-----------------------------|
| `ROLE_ADMIN` | Oui | Oui | Oui (identique au manager pour la clôture) |
| `ROLE_MANAGER` + `ROLE_USER` | Oui | Oui | Oui |
| `ROLE_USER` seul | Oui | Non (pas de formulaire de clôture) | Oui (y compris CRUD conducteurs tel que codé aujourd’hui) |
| `ROLE_INSPECTOR` seul | Non (pas `ROLE_USER`) | Non | Non — orienté formulaire public |

## Fichiers utiles pour maintenir cette doc

- `src/Controller/DashboardController.php` — agrégation des métriques et pagination de la liste.
- `src/Repository/ReportRepository.php` — requêtes comptage, graphiques, listes « management ».
- `templates/dashboard/index.html.twig` — présentation et interactions (filtres graphiques).
- `src/Controller/ReportController.php` — liste, détail, clôture (`ROLE_MANAGER` / `ROLE_ADMIN`).
- `config/packages/security.yaml` — contrôle d’accès global.
- `src/DataFixtures/ManagerUserFixtures.php` — compte manager de démonstration.
