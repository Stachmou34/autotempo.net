<?php
/**
 * API MCJ-Courtage — relances de fin de contrat (JSON, HTTPS uniquement).
 *
 * Authentification : en-tête  Authorization: Bearer <jeton>
 *   (si l'hébergeur retire cet en-tête, envoyer le même contenu dans  X-Authorization)
 * Jetons : créés / listés / révoqués avec  php api_token.php  (voir ce fichier).
 *
 * GET  /api.php?route=echeances&de=0&a=7
 *      Contrats valides (statut V) dont la date de fin est entre aujourd'hui+de et aujourd'hui+a jours
 *      (plage max 93 jours). Les contrats déjà relancés ne sont plus renvoyés.
 *      200 {"du","au","nombre","contrats":[{ref,email,prenom,date_debut,date_fin,duree_jours,categorie,montant}]}
 *
 * POST /api.php?route=relances   corps JSON : {"ref":"c_..."}
 *      Marque la relance comme faite (idempotent).
 *      200 {"ref","relance_le","deja_faite"}   404 si la référence n'a jamais été renvoyée par echeances.
 *
 * Erreurs : {"erreur": code, "message": texte} avec 400, 401, 403, 404, 405 ou 500.
 */
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', '0');
// Nombres JSON sous leur forme courte (159.37 et non 159.3700000000000045…),
// quel que soit le serialize_precision du php.ini de l'hébergeur (PHP 7.1+).
ini_set('serialize_precision', '-1');
require dirname(__FILE__) . '/api_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// En-tête Authorization (plusieurs emplacements selon la configuration CGI / suPHP de l'hébergeur).
$auth = '';
foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_AUTHORIZATION') as $k) {
    if (!empty($_SERVER[$k])) { $auth = $_SERVER[$k]; break; }
}
if ($auth === '' && function_exists('apache_request_headers')) {
    foreach (apache_request_headers() as $nom => $val) {
        if (strtolower($nom) === 'authorization') { $auth = $val; break; }
    }
}

$route = isset($_GET['route']) ? (string) $_GET['route'] : '';
if ($route === '' && !empty($_SERVER['PATH_INFO'])) { $route = trim($_SERVER['PATH_INFO'], '/'); }

$req = array(
    'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
    'route'  => $route,
    'auth'   => $auth,
    'query'  => $_GET,
    'body'   => file_get_contents('php://input'),
    'post'   => $_POST,
    'https'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'),
);

$dir = apiDataDir();
$getPdo = function () {
    $db = parse_ini_file(dirname(__FILE__) . '/db.ini');
    if (!$db) { throw new Exception('db.ini manquant'); }
    $pdo = new PDO('mysql:host=' . (isset($db['host']) ? $db['host'] : 'localhost')
        . ';dbname=' . $db['base'] . ';charset=utf8', $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
};

try {
    apiEnsureDir($dir);
    list($status, $payload) = apiHandle($req, $getPdo, $dir);
} catch (Exception $e) {
    @apiLog($dir, 'ERREUR ' . $route . ' : ' . $e->getMessage());
    error_log('MCJ API : ' . $e->getMessage()); // si le dossier de données est inaccessible, api.log l'est aussi
    $status = 500;
    $payload = array('erreur' => 'serveur', 'message' => 'Erreur interne');
}

if ($status === 401) { header('WWW-Authenticate: Bearer realm="mcj-api"'); }
http_response_code($status);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@apiLog($dir, (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-') . ' ' . $req['method'] . ' ' . $route . ' -> ' . $status);
