<?php
/**
 * Amorçage commun : chargement de la config, connexions PDO, session.
 * Inclus au début de chaque page.
 *
 * Compatible PHP 5.6+.
 */

// Affichage des erreurs uniquement en développement (désactivé par défaut).
error_reporting(E_ALL);
ini_set('display_errors', '0');

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration manquante : copiez config/config.example.php en config/config.php');
}

/** @var array $CONFIG */
$CONFIG = require $configFile;

$tz = isset($CONFIG['app']['timezone']) ? $CONFIG['app']['timezone'] : 'Europe/Paris';
date_default_timezone_set($tz);

require __DIR__ . '/Database.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/ContractRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
