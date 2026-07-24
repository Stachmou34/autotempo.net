# MCJ-Courtage — Gestion des contrats REYNARD

Application PHP/MySQL de gestion des contrats de l'apporteur **REYNARD**,
avec récupération des contrats depuis la base **JLASSURE**.

## Principe

- **JLASSURE** = source des contrats, en **lecture seule** (jamais modifiée).
- **MCJ-Courtage** = base propre à l'application pour le **suivi de gestion**
  (statuts, commissions, notes, historique), reliée aux contrats JLASSURE
  par leur référence.

L'appli lit les contrats de l'apporteur REYNARD chez JLASSURE, les fusionne
avec les données de gestion MCJ, et propose : liste/recherche, suivi des
statuts, édition, et export CSV.

## Fonctionnalités

- 📋 Liste des contrats REYNARD avec recherche et filtre par statut
- 📊 Tableau de bord (nombre de contrats, primes cumulées, commissions)
- ✏️ Suivi et édition : statut, commission, commission payée, notes
- ⬇️ Export CSV (compatible Excel)
- 🔍 Outil de découverte du schéma JLASSURE (`/tools/schema.php`)
- 🔐 Accès protégé par identifiant + mot de passe, protection CSRF

## Installation sur le serveur

### 1. Récupérer le code dans `public_html`

⚠️ Sauvegardez d'abord tout contenu existant que vous voulez garder.

```bash
cd ~/public_html

# Vider public_html (⚠️ supprime le contenu actuel — sauvegardez avant !)
# On garde le dossier .git s'il existe déjà.
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +

# Récupérer cette branche depuis GitHub
git fetch origin
git checkout claude/git-main-push-error-gf3x5i   # ou la branche fusionnée dans main
git pull origin claude/git-main-push-error-gf3x5i
```

### 2. Créer la configuration

```bash
cp config/config.example.php config/config.php
nano config/config.php
```

Renseignez :
- les identifiants de connexion à **JLASSURE** (`jlassure_db`) — idéalement
  un utilisateur MySQL en **lecture seule** ;
- les identifiants de la base **MCJ** (`mcj_db`) ;
- le compte d'accès (`auth`). Générez le hash du mot de passe :
  ```bash
  php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT), PHP_EOL;"
  ```

### 3. Créer la base de gestion MCJ

```bash
mysql -u mcj_user -p mcj_courtage < sql/mcj_schema.sql
```

### 4. Ajuster le mapping JLASSURE

Le nom des tables/colonnes de JLASSURE varie. Connectez-vous à l'appli puis
ouvrez **`/tools/schema.php`** : il liste les tables et colonnes réelles.
Reportez les bons noms dans `config/config.php` → section `jlassure_mapping` :

- `table` : la table des contrats
- `key` : la colonne identifiant unique du contrat
- `apporteur_column` : la colonne du nom d'apporteur (pour filtrer REYNARD)
- `fields` : correspondance champ ↔ colonne (référence, client, prime…)

### 5. C'est prêt

Ouvrez le site dans le navigateur → page de connexion → liste des contrats.

## Sécurité

- `config/config.php` n'est **jamais** commité (voir `.gitignore`).
- `config/config.php` ne renvoie qu'un tableau PHP (aucune sortie) : même
  accédé directement en HTTP, il ne divulgue rien tant que PHP est actif.
- Toutes les requêtes SQL utilisent des requêtes préparées.
- Utilisez de préférence un utilisateur MySQL **en lecture seule** pour JLASSURE.

> ℹ️ Les fichiers `.htaccess` ont été retirés : cet hébergeur refuse certaines
> directives (`Options`), ce qui provoquait une erreur 500. Si tu veux durcir
> l'accès (interdire le listing des dossiers, bloquer `.git/`), on pourra
> ajouter un `.htaccess` minimal une fois connues les directives autorisées
> par ton hébergeur (`AllowOverride`).

## Structure

```
├── index.php            Liste + tableau de bord
├── contract.php         Détail + édition de la gestion
├── export.php           Export CSV
├── login.php / logout.php
├── config/config.php    Vos identifiants (non commité)
├── src/                 Logique (DB, Auth, dépôt contrats)
├── partials/            Gabarit header/footer
├── tools/schema.php     Découverte du schéma JLASSURE
├── sql/mcj_schema.sql   Schéma de la base MCJ
└── assets/css/style.css
```
