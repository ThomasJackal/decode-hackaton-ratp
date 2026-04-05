# Profil « client mystère » (`ROLE_INSPECTOR`) — fonctionnement et périmètre

Dans l’application, ce profil correspond au rôle technique Symfony **`ROLE_INSPECTOR`**. Côté métier, on le nomme **client mystère** : il s’agit d’un **agent qui emprunte les transports comme un usager**, sans se signaler comme personnel, et dont la mission est de **produire des signalements factuels et exploitables**. La **crédibilité** de ces rapports est un enjeu central : en amont, elle conditionne la confiance des équipes ; en aval, les traitements (dont les **modèles d’IA** qui enrichissent ou classent les signalements) peuvent **pondérer plus fortement** une déclaration issue d’un compte client mystère identifié que celle d’un voyageur totalement anonyme.

---

## Accès et rôles

| Élément | Détail |
|--------|--------|
| **Rôle Symfony** | `ROLE_INSPECTOR` (sans `ROLE_USER` dans la fixture de démo). |
| **Compte de démo** | `InspectorUserFixtures` : utilisateur `inspector`, mot de passe indiqué dans la fixture (`InspectorFixture!2026`). |
| **Accès HTTP** | Comme tout le monde : seules les routes **publiques** ou avec un rôle adapté sont accessibles. Le pare-feu principal impose **`ROLE_USER`** pour tout le site sauf exceptions (`config/packages/security.yaml`). Les routes du **formulaire de signalement** (`^/report/new`, bus finder, suggestions transit, page de remerciement) sont en **accès public** : le client mystère peut donc remplir le formulaire **connecté ou non**. En pratique, la connexion sert à **identifier l’agent** et à personnaliser l’entête du site (voir ci-dessous). |
| **Après connexion** | `LoginFormAuthenticator` : si le jeton contient `ROLE_INSPECTOR` **et pas** `ROLE_USER`, la redirection par défaut va vers **`app_report_new`** (formulaire de signalement), **pas** vers le tableau de bord. |

Le client mystère **n’a pas** le rôle `ROLE_USER` dans les données de fixture actuelles : il **n’est pas** considéré comme utilisateur « espace gestion » au sens du bandeau navigation.

---

## Visibilité (interface)

| Zone | Client mystère |
|------|----------------|
| **Tableau de bord** (`/`) | **Non** — réservé aux comptes avec au moins `ROLE_USER` au regard de la règle d’accès globale. Même en essayant d’ouvrir `/`, l’accès est refusé tant que le rôle ne correspond pas. |
| **Listes / fiches signalements, conducteurs** | **Non** — mêmes routes protégées par `ROLE_USER` ; pas d’accès back-office. |
| **Formulaire voyageur** | **Oui** — page `/report/new` (et flux associés publics). |
| **En-tête** | `templates/layout/_header.html.twig` : pour un utilisateur connecté **sans** `is_granted('ROLE_USER')`, la navigation affiche uniquement **Signalement** (vers le formulaire) et **Déconnexion**. Le gabarit base marque les comptes ayant `ROLE_INSPECTOR` pour un style visuel distinct (`site-header--inspector`, dégradé bleu). |

En résumé : le client mystère voit une **interface réduite**, centrée sur la **production de signalements**, et **aucune vue agrégée** sur les données internes.

---

## Actions possibles

1. **Se connecter** — équipe / agent reconnu.
2. **Remplir et soumettre** le formulaire public de signalement (identification ligne / arrêt / direction ou bus prérempli, date de trajet, description, coordonnées de contact optionnelles selon le formulaire, etc.), avec les aides prévues (recherche bus, suggestions transit, **dictée vocale** côté navigateur si activée sur la page).
3. **Accéder à la page de remerciement** après envoi réussi (`/report/thanks`).
4. **Se déconnecter**.

**Ce qu’il ne peut pas faire** (avec le rôle tel que défini aujourd’hui) : clôturer des dossiers, consulter le détail des signalements en interne, gérer les conducteurs, ou naviguer sur le dashboard — ces actions sont du ressort de `ROLE_USER` (et la clôture : `ROLE_MANAGER` / `ROLE_ADMIN`).

---

## Crédibilité du rapport et IA

### Modèle de données

L’entité `Report` expose un champ **`reportCredibility`** (chaîne, typiquement des valeurs du type `low` / `medium` / `high`, ou une valeur métier). Ce champ est :

- renseignable lors de la création via l’**API** `POST /api/reports` (`ApiReportController`, clés `report_credibility` ou `reportCredibility`) ;
- affiché dans les vues **fiche signalement**, **liste des signalements** et **fiche conducteur** (libellés français selon la valeur).

Les signalements issus des **fixtures** ou du pipeline API peuvent donc porter une **crédibilité explicite** exploitable par des outils ou une IA en aval.

### Formulaire web (`/report/new`)

Le flux actuel envoie un **corps JSON** au service configuré par `REPORT_WEBHOOK_URL`. Dans `ReportController::new`, le payload inclut notamment `description`, conducteur, bus, dates, contact, et un bloc **`metadata`** avec `source: formulaire` et le contexte **finder / transit**. **Le JSON envoyé au webhook ne contient pas aujourd’hui** un identifiant d’utilisateur Symfony ni un drapeau « client mystère », et **ne fixe pas** `report_credibility` dans ce payload.

**Conséquence pour le produit** : la **forte crédibilité** attendue des rapports client mystère repose soit sur une **évolution du webhook** (par ex. enrichissement avec l’utilisateur authentifié et un `reporter_profile: mystery_shopper`), soit sur la **logique côté service qui reçoit le webhook** qui relie la session / le compte à une pondération IA. Côté métier, c’est cohérent avec la mission « usager discret » : l’agent utilise le **même parcours** qu’un voyageur, mais le **compte authentifié** permet de **tracer** l’origine et d’**augmenter le poids** du signalement dans les analyses automatiques **si le pipeline le prévoit**.

---

## Comparaison rapide avec les autres profils

| Profil | Dashboard / gestion | Formulaire signalement | Clôture signalements |
|--------|---------------------|------------------------|----------------------|
| **Client mystère** (`ROLE_INSPECTOR` seul) | Non | Oui (parcours public, entête dédiée) | Non |
| **`ROLE_USER`** (sans manager) | Oui | Oui (lien depuis la nav) | Non |
| **`ROLE_MANAGER` / `ROLE_ADMIN`** | Oui | Oui | Oui |

---

## Fichiers utiles pour maintenir cette doc

- `src/DataFixtures/InspectorUserFixtures.php` — compte de démo et rôles.
- `src/Security/LoginFormAuthenticator.php` — redirection post-login vers le formulaire.
- `config/packages/security.yaml` — `access_control` et routes publiques du signalement.
- `templates/layout/_header.html.twig` — navigation minimale pour les inspecteurs.
- `templates/base.html.twig` — détection `ROLE_INSPECTOR` pour le style d’en-tête.
- `src/Controller/ReportController.php` — action `new`, construction du payload webhook.
- `src/Entity/Report.php` — champ `reportCredibility`.
- `src/Controller/Api/ApiReportController.php` — persistance avec `report_credibility`.
