<?php
/**
 * Commun aux tâches planifiées (cron). EXÉCUTION EN LIGNE DE COMMANDE UNIQUEMENT.
 * Fournit : $pdo (JLASSURE), $ids (apporteur REYNARD), et la fonction envoyerMail().
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only\n"); }
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$DIR = __DIR__;

// ── Base JLASSURE (réutilise db.ini) ──────────────────────────────
$db = is_file($DIR . '/db.ini') ? parse_ini_file($DIR . '/db.ini') : null;
if (!$db) { fwrite(STDERR, "db.ini manquant\n"); exit(1); }

// ── Paramètres de notification (notif.ini) ────────────────────────
$notif = is_file($DIR . '/notif.ini') ? parse_ini_file($DIR . '/notif.ini') : array();
$EMAIL_TO   = isset($notif['email']) ? trim($notif['email']) : '';
$EMAIL_FROM = isset($notif['from']) && trim($notif['from']) !== '' ? trim($notif['from']) : 'noreply@autotempo.net';
if ($EMAIL_TO === '') { fwrite(STDERR, "notif.ini : adresse 'email' destinataire manquante\n"); exit(1); }

// ── Connexion ─────────────────────────────────────────────────────
try {
    $pdo = new PDO('mysql:host=' . (isset($db['host']) ? $db['host'] : 'localhost')
        . ';dbname=' . $db['base'] . ';charset=utf8', $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    fwrite(STDERR, 'Connexion DB : ' . $e->getMessage() . "\n"); exit(1);
}

// ── Apporteur REYNARD (id, société, marque blanche) ───────────────
$APPORTEUR = 'REYNARD';
$stmt = $pdo->prepare("SELECT id, nom, prenom, societe, commentaire FROM jl_app WHERE (nom LIKE :q OR prenom LIKE :q OR societe LIKE :q) AND status = 'V'");
$stmt->execute(array(':q' => '%' . $APPORTEUR . '%'));
$ids = array(); $apInfo = array();
foreach ($stmt->fetchAll() as $a) {
    $id = (int) $a['id']; $ids[] = $id;
    $com = explode('|', (string) $a['commentaire']);
    $soc = ($a['societe'] !== '' && $a['societe'] !== null) ? $a['societe'] : trim($a['nom'] . ' ' . $a['prenom']);
    $apInfo[$id] = array('mb' => (isset($com[2]) && $com[2] == '1') ? 1 : 0, 'societe' => $soc);
}

/** Parse un nombre "1 234,56" -> float. */
function numv($v) { return (float) str_replace(array(' ', ','), array('', '.'), (string) $v); }

/**
 * Rétro globale d'un contrat (RC DR + honoraires), même calcul que le bordereau.
 * $r doit contenir : note3, pa, r_marge, r_hono, prix_assitance, prix_pj, id_lb2, etat.
 */
function retroContrat($r, $mb) {
    $etat = isset($r['etat']) ? $r['etat'] : '';
    $montant = numv(isset($r['note3']) ? $r['note3'] : 0);
    if ($etat === 'A' || $etat === 'R') { $montant = 0; }
    $prime_esc = numv(isset($r['pa']) ? $r['pa'] : 0);
    $pv = $prime_esc + numv(isset($r['r_marge']) ? $r['r_marge'] : 0) + numv(isset($r['r_hono']) ? $r['r_hono'] : 0);
    $prix_ass = numv(isset($r['prix_assitance']) ? $r['prix_assitance'] : 0);
    $prix_dr = numv(isset($r['prix_pj']) ? $r['prix_pj'] : 0);
    if ($mb == 1) { $rcdr = round(($pv - $prime_esc) * 0.4764, 2); $hono = round(numv(isset($r['id_lb2']) ? $r['id_lb2'] : 0), 2); }
    else          { $rcdr = round($montant - $pv - $prix_ass - $prix_dr, 2); $hono = 0; }
    if ($rcdr <= 0) { $rcdr = 0; } if ($hono <= 0) { $hono = 0; }
    return $rcdr + $hono;
}

// ── Utilitaires ───────────────────────────────────────────────────
function hh($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function eur($v) { return number_format((float) str_replace(array(' ', ','), array('', '.'), (string) $v), 2, ',', ' ') . ' €'; }
function dfr($v) { $t = strtotime((string) $v); return $t ? date('d/m/Y', $t) : ''; }

/** Envoi d'un email HTML (UTF-8). */
function envoyerMail($to, $from, $sujet, $html) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: MCJ-Courtage <" . $from . ">\r\n";
    $sujetEnc = '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    return mail($to, $sujetEnc, $html, $headers);
}

/** Journalise un évènement dans logs/<nom>.log (avec horodatage). */
function logCron($nom, $msg) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0755); }
    @file_put_contents($dir . '/' . $nom . '.log', date('Y-m-d H:i:s') . '  ' . $msg . "\n", FILE_APPEND);
    echo date('H:i:s') . '  ' . $msg . "\n";
}

/** Gabarit HTML commun d'un email. */
function gabaritMail($titre, $corps) {
    return '<div style="font-family:Arial,sans-serif;color:#222;max-width:800px">'
         . '<h2 style="color:#1f5eff">' . hh($titre) . '</h2>' . $corps
         . '<p style="color:#888;font-size:12px;margin-top:24px">MCJ-Courtage — notification automatique (source JLASSURE).</p></div>';
}
