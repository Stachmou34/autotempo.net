<?php
/**
 * MCJ-Courtage — Gestion des contrats REYNARD (source JLASSURE)
 * ------------------------------------------------------------------
 * Copiez ce fichier en `config/config.php` sur le serveur et
 * renseignez vos identifiants réels. Le fichier `config.php` est
 * ignoré par git (.gitignore) : vos mots de passe ne partiront JAMAIS
 * sur GitHub.
 *
 *   cp config/config.example.php config/config.php
 *   nano config/config.php
 */

return [

    // ── Application ────────────────────────────────────────────────
    'app' => [
        'name'      => 'MCJ-Courtage — Gestion des contrats',
        // Filtre appliqué sur le nom de l'apporteur côté JLASSURE.
        // On récupère uniquement les contrats de cet apporteur.
        'apporteur' => 'REYNARD',
        // 'like' = correspondance partielle (%REYNARD%) — recommandé si
        // le nom peut être stocké "REYNARD Jean" ou "M. REYNARD".
        // 'exact' = correspondance stricte.
        'apporteur_match' => 'like',
        'timezone'  => 'Europe/Paris',
    ],

    // ── Authentification (accès à l'application) ───────────────────
    // Générez le hash du mot de passe avec :
    //   php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT), PHP_EOL;"
    'auth' => [
        'username'      => 'admin',
        'password_hash' => '$2y$10$REMPLACEZ_MOI_PAR_UN_VRAI_HASH_GENERE',
    ],

    // ── Base de données JLASSURE (LECTURE SEULE) ───────────────────
    // Source des contrats. L'application n'écrit JAMAIS ici.
    'jlassure_db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'jlassure',
        'user'    => 'jlassure_ro',       // idéalement un utilisateur en lecture seule
        'pass'    => 'MOT_DE_PASSE_JLASSURE',
        'charset' => 'utf8mb4',
    ],

    // ── Base de données MCJ (gestion : statuts, notes, commissions) ─
    // Base propre à l'application. Peut être la même que ci-dessus si
    // vous préférez, mais une base séparée est plus propre.
    'mcj_db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'mcj_courtage',
        'user'    => 'mcj_user',
        'pass'    => 'MOT_DE_PASSE_MCJ',
        'charset' => 'utf8mb4',
    ],

    // ── Mapping du schéma JLASSURE ─────────────────────────────────
    // ⚠️ À AJUSTER selon la vraie structure de JLASSURE.
    // Utilisez l'outil de découverte : /tools/schema.php pour lister
    // les tables et colonnes réelles, puis corrigez ci-dessous.
    'jlassure_mapping' => [
        // Nom de la table qui contient les contrats.
        'table' => 'contrats',

        // Colonne servant de référence unique du contrat (clé de liaison
        // avec les données de gestion MCJ).
        'key' => 'id',

        // Colonne contenant le nom de l'apporteur (pour filtrer REYNARD).
        'apporteur_column' => 'apporteur',

        // Correspondance "champ logique de l'appli" => "colonne JLASSURE".
        // Mettez null pour un champ absent chez JLASSURE.
        'fields' => [
            'reference'       => 'numero_contrat',
            'date_effet'      => 'date_effet',
            'date_souscription' => 'date_souscription',
            'client_nom'      => 'client_nom',
            'client_prenom'   => 'client_prenom',
            'produit'         => 'produit',
            'compagnie'       => 'compagnie',
            'prime'           => 'prime_annuelle',
            'statut_source'   => 'statut',
        ],
    ],
];
