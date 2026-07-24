-- =====================================================================
-- MCJ-Courtage — Base de gestion (statuts, commissions, notes)
-- ---------------------------------------------------------------------
-- À importer dans la base `mcj_courtage` (voir config/config.php).
--   mysql -u mcj_user -p mcj_courtage < sql/mcj_schema.sql
-- =====================================================================

SET NAMES utf8mb4;

-- Données de gestion propres à MCJ, reliées à chaque contrat JLASSURE
-- par sa référence (jlassure_ref = colonne "key" du mapping).
CREATE TABLE IF NOT EXISTS `contrat_gestion` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jlassure_ref`   VARCHAR(191) NOT NULL COMMENT 'Référence du contrat côté JLASSURE',
    `statut`         ENUM(
                        'nouveau',
                        'en_cours',
                        'valide',
                        'en_attente',
                        'impaye',
                        'resilie',
                        'annule'
                     ) NOT NULL DEFAULT 'nouveau',
    `commission`     DECIMAL(10,2) DEFAULT NULL COMMENT 'Commission apporteur (€)',
    `commission_payee` TINYINT(1) NOT NULL DEFAULT 0,
    `notes`          TEXT DEFAULT NULL,
    `updated_by`     VARCHAR(100) DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ref` (`jlassure_ref`),
    KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des modifications (traçabilité).
CREATE TABLE IF NOT EXISTS `contrat_historique` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `jlassure_ref` VARCHAR(191) NOT NULL,
    `action`       VARCHAR(50) NOT NULL,
    `details`      TEXT DEFAULT NULL,
    `utilisateur`  VARCHAR(100) DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ref` (`jlassure_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
