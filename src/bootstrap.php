<?php
/**
 * Amorçage commun : chargement de la config, connexions PDO, session.
 * Inclus au début de chaque page.
 *
 * Compatible PHP 5.6+.
 */

// ── Phase de mise en service : erreurs visibles pour diagnostic ────
// TODO : repasser display_errors à '0' une fois le site validé.
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Capte les erreurs fatales et les affiche en clair (statut 200) afin
// d'éviter le "500 Internal Server Error" masqué par Apache.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR), true)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<h2 style="color:#c02b2b;font-family:sans-serif">Erreur PHP</h2>';
        echo '<pre style="background:#fde3e3;padding:12px;border-radius:6px;white-space:pre-wrap">'
            . htmlspecialchars($e['message'] . "\n\nFichier : " . $e['file'] . ' (ligne ' . $e['line'] . ')')
            . '</pre>';
    }
});

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2 style="font-family:sans-serif">Configuration manquante</h2>';
    echo '<p style="font-family:sans-serif">Créez le fichier <code>config/config.php</code> :</p>';
    echo '<pre style="background:#eef;padding:12px;border-radius:6px">cp config/config.example.php config/config.php</pre>';
    echo '<p style="font-family:sans-serif">puis renseignez vos identifiants JLASSURE, MCJ et le compte admin.</p>';
    exit;
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
