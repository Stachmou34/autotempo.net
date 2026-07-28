<?php
// ===================================================================
//  MCJ-Courtage — Portail sécurisé + Bordereau rétrocession REYNARD
//  Source JLASSURE. Fichier unique. Secrets dans db.ini / auth.ini (403).
// ===================================================================
error_reporting(E_ALL);
ini_set('display_errors', '1'); // TODO: '0' en production
ini_set('session.cookie_httponly', '1');
session_start();
header('Content-Type: text/html; charset=utf-8');
ob_start(); // bufferise la sortie : permet de basculer en CSV à la demande
$EXPORT = isset($_GET['export']) ? $_GET['export'] : '';

$APPORTEUR = 'REYNARD';       // nom recherché dans la base JLASSURE
$LABEL     = 'MCJ COURTAGE';  // libellé affiché à l'écran
$DEPUIS    = '2026-01-01';   // n'afficher que les contrats depuis 2026
$DIR       = dirname(__FILE__);
$dbFile    = $DIR . '/db.ini';
$authFile  = $DIR . '/auth.ini';

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function num($v) { return (float) str_replace(array(' ', ','), array('', '.'), (string) $v); }
/** Catégories véhicule soumises à la règle "1 € camion" en marque blanche. */
function catCam($c) { return in_array((string) $c, array('TCP', 'CAM3', 'CAM4', 'REM2', 'REM3', 'TRA'), true); }
/** Sort un CSV (jette le HTML bufferisé) et arrête le script. $rows[0] = entêtes. */
function csvOut($filename, $rows) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
    foreach ($rows as $r) { fputcsv($out, $r, ';'); }
    fclose($out);
    exit;
}
/** Lien "Exporter CSV" conservant les filtres courants. */
function lienCsv($extra = array()) {
    $q = array_merge($_GET, array('export' => 'csv'), $extra);
    return '?' . http_build_query($q);
}
function euros($v) { return number_format((float) $v, 2, ',', ' ') . ' €'; }
function dateFr($v) {
    if (empty($v) || $v === '0000-00-00') { return '—'; }
    $ts = strtotime($v);
    return $ts ? date('d/m/Y', $ts) : h($v);
}
function validDate($d, $def) {
    $d = trim((string) $d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : $def;
}
function jeton() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = function_exists('random_bytes')
            ? bin2hex(random_bytes(16)) : bin2hex(openssl_random_pseudo_bytes(16));
    }
    return $_SESSION['csrf'];
}
function jetonOk() {
    return isset($_POST['csrf']) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
function entete($titre) {
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($titre) . '</title>'
       . '<style>' . css() . '</style></head><body>';
}
function css() {
    return 'body{font-family:system-ui,Arial,sans-serif;max-width:1720px;margin:0 auto;padding:0 16px 40px;line-height:1.5;color:#222;background:#f4f6fb}'
      . 'h1{color:#1f5eff;margin-bottom:2px} h2{margin-top:24px;border-bottom:1px solid #e3e8f0;padding-bottom:6px}'
      . 'code{background:#eef;padding:2px 6px;border-radius:4px} pre{background:#f6f8fc;border:1px solid #dde3ee;padding:12px;border-radius:8px;overflow:auto;white-space:pre-wrap}'
      . '.ok{color:#1a7d49;font-weight:bold} .no{color:#c02b2b;font-weight:bold} .muted{color:#888}'
      . '.nav{margin:12px 0 6px;border-bottom:2px solid #e3e8f0} .nav a{display:inline-block;padding:8px 16px;text-decoration:none;color:#555;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px} .nav a.on{color:#1f5eff;border-bottom-color:#1f5eff}'
      . 'table{border-collapse:collapse;width:100%;margin-top:10px;background:#fff;font-size:12px}'
      . 'th,td{border:1px solid #e3e8f0;padding:5px 7px;text-align:left;white-space:nowrap}'
      . 'th{background:#f7f9fd} .num{text-align:right} .ctr{text-align:center}'
      . '.box{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-top:20px}'
      . '.stats{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0}'
      . '.stat{background:#fff;border:1px solid #e3e8f0;border-radius:8px;padding:12px 18px;min-width:150px}'
      . '.stat b{display:block;font-size:22px;color:#1f5eff} .stat span{color:#666;font-size:13px}'
      . '.stat.hl b{color:#1a7d49}'
      . '.topbar{display:flex;justify-content:space-between;align-items:center;background:#fff;border-bottom:1px solid #e3e8f0;padding:12px 18px;margin:0 -16px 20px}'
      . '.topbar .brand{font-weight:700;color:#1f5eff;font-size:18px} .topbar .right{font-size:14px;color:#555}'
      . '.topbar a{color:#c02b2b;text-decoration:none;margin-left:14px}'
      . '.filtres{margin:14px 0;font-size:14px;line-height:2.2} .filtres select,.filtres button,.filtres input{font:inherit;padding:6px 10px;border:1px solid #ccd4e2;border-radius:6px}'
      . '.filtres button{background:#1f5eff;color:#fff;border:0;cursor:pointer}'
      . '.legende{font-size:12px;color:#666;margin-top:8px} .legende span{display:inline-block;padding:2px 8px;border-radius:4px;margin-right:6px}'
      . '.card{max-width:380px;margin:60px auto;background:#fff;border:1px solid #dde3ee;border-radius:10px;padding:28px;box-shadow:0 6px 24px rgba(20,30,60,.06)}'
      . '.card h1{margin-top:0} .card label{display:block;margin-bottom:14px;font-weight:600}'
      . '.card input{width:100%;padding:10px 12px;border:1px solid #ccd4e2;border-radius:6px;font:inherit;font-weight:400;box-sizing:border-box}'
      . '.card button{width:100%;background:#1f5eff;color:#fff;border:0;padding:11px;border-radius:6px;font:inherit;font-weight:600;cursor:pointer}'
      . '.alert{padding:10px 14px;border-radius:6px;margin-bottom:16px} .alert.err{background:#fde3e3;color:#a12}'
      . 'form.cfg{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:20px;max-width:420px}'
      . 'form.cfg label{display:block;margin-bottom:14px;font-weight:600} form.cfg input{width:100%;padding:9px 11px;border:1px solid #ccd4e2;border-radius:6px;font:inherit;font-weight:400;box-sizing:border-box}'
      . 'form.cfg button{background:#1f5eff;color:#fff;border:0;padding:10px 18px;border-radius:6px;font:inherit;cursor:pointer}'
      . 'a{color:#1f5eff} .tools{font-size:13px;color:#888;margin-top:30px}';
}

// ── Deconnexion ───────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = array(); session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

// ── Compte administrateur ─────────────────────────────────────────
$auth = is_file($authFile) ? parse_ini_file($authFile) : null;
$authUser = ($auth && isset($auth['user'])) ? $auth['user'] : '';
$authHash = ($auth && isset($auth['hash'])) ? base64_decode($auth['hash']) : '';
$erreurAuth = '';

if ($auth === null && isset($_POST['creer_compte'])) {
    if (!jetonOk()) { $erreurAuth = 'Session expirée, réessaie.'; }
    else {
        $u = trim(isset($_POST['user']) ? $_POST['user'] : '');
        $p = isset($_POST['pass']) ? $_POST['pass'] : '';
        $p2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';
        if ($u === '' || strlen($p) < 6) { $erreurAuth = 'Identifiant requis, mot de passe 6 caractères min.'; }
        elseif ($p !== $p2) { $erreurAuth = 'Les mots de passe ne correspondent pas.'; }
        else {
            $contenu = 'user = "' . str_replace('"', '', $u) . '"' . "\n"
                     . 'hash = "' . base64_encode(password_hash($p, PASSWORD_DEFAULT)) . '"' . "\n";
            if (@file_put_contents($authFile, $contenu) !== false) {
                @chmod($authFile, 0600); session_regenerate_id(true); $_SESSION['user'] = $u;
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
            }
            $erreurAuth = 'Impossible d\'écrire auth.ini.';
        }
    }
}
if ($auth !== null && isset($_POST['connexion'])) {
    if (!jetonOk()) { $erreurAuth = 'Session expirée, réessaie.'; }
    else {
        $u = trim(isset($_POST['user']) ? $_POST['user'] : '');
        $p = isset($_POST['pass']) ? $_POST['pass'] : '';
        if (hash_equals($authUser, $u) && password_verify($p, $authHash)) {
            session_regenerate_id(true); $_SESSION['user'] = $authUser;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        $erreurAuth = 'Identifiant ou mot de passe incorrect.';
    }
}
$connecte = !empty($_SESSION['user']);

// ── ECRAN création compte ─────────────────────────────────────────
if ($auth === null) {
    entete('MCJ-Courtage — Créer le compte');
    echo '<div class="card"><h1>Premier accès</h1><p class="muted">Crée le compte administrateur.</p>';
    if ($erreurAuth) { echo '<div class="alert err">' . h($erreurAuth) . '</div>'; }
    echo '<form method="post"><input type="hidden" name="csrf" value="' . h(jeton()) . '">'
       . '<label>Identifiant<input type="text" name="user" required autofocus></label>'
       . '<label>Mot de passe (6 min.)<input type="password" name="pass" required></label>'
       . '<label>Confirmer<input type="password" name="pass2" required></label>'
       . '<button type="submit" name="creer_compte" value="1">Créer le compte</button></form></div></body></html>';
    exit;
}
// ── ECRAN connexion ───────────────────────────────────────────────
if (!$connecte) {
    entete('MCJ-Courtage — Connexion');
    echo '<div class="card"><h1>Connexion</h1>';
    if ($erreurAuth) { echo '<div class="alert err">' . h($erreurAuth) . '</div>'; }
    echo '<form method="post"><input type="hidden" name="csrf" value="' . h(jeton()) . '">'
       . '<label>Identifiant<input type="text" name="user" required autofocus></label>'
       . '<label>Mot de passe<input type="password" name="pass" required></label>'
       . '<button type="submit" name="connexion" value="1">Se connecter</button></form></div></body></html>';
    exit;
}

// ================================================================
//  ZONE PROTEGEE
// ================================================================
entete('MCJ-Courtage — Bordereau ' . $LABEL);
echo '<div class="topbar"><span class="brand">MCJ-Courtage</span>'
   . '<span class="right">👤 ' . h($_SESSION['user']) . ' <a href="?logout=1">Déconnexion</a></span></div>';

// Config base
$messageEcriture = '';
if (isset($_POST['enregistrer_config'])) {
    if (!jetonOk()) { $messageEcriture = 'Session expirée.'; }
    else {
        $contenu = 'host = "' . str_replace('"', '', trim($_POST['host'])) . '"' . "\n"
                 . 'base = "' . str_replace('"', '', trim($_POST['base'])) . '"' . "\n"
                 . 'user = "' . str_replace('"', '', trim($_POST['user'])) . '"' . "\n"
                 . 'pass = "' . str_replace('"', '', $_POST['pass']) . '"' . "\n";
        if (@file_put_contents($dbFile, $contenu) !== false) {
            @chmod($dbFile, 0600); header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        $messageEcriture = "Impossible d'écrire db.ini.";
    }
}
$cfg = is_file($dbFile) ? parse_ini_file($dbFile) : null;
$pdo = null; $erreur = '';
if ($cfg !== null) {
    try {
        $pdo = new PDO('mysql:host=' . (isset($cfg['host']) ? $cfg['host'] : 'localhost')
            . ';dbname=' . (isset($cfg['base']) ? $cfg['base'] : '') . ';charset=utf8',
            isset($cfg['user']) ? $cfg['user'] : '', isset($cfg['pass']) ? $cfg['pass'] : '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $e) { $erreur = $e->getMessage(); }
}

echo '<h1>Bordereau rétrocession — <span style="color:#1f5eff">' . h($LABEL) . '</span></h1>'
   . '<p class="muted">Source JLASSURE</p>';

if ($cfg === null) {
    if ($messageEcriture) { echo '<div class="alert err">' . h($messageEcriture) . '</div>'; }
    echo '<h2>Configuration de la base JLASSURE</h2>'
       . '<form class="cfg" method="post"><input type="hidden" name="csrf" value="' . h(jeton()) . '">'
       . '<label>Serveur<input type="text" name="host" value="localhost" required></label>'
       . '<label>Nom de la base<input type="text" name="base" required></label>'
       . '<label>Utilisateur MySQL<input type="text" name="user" required></label>'
       . '<label>Mot de passe MySQL<input type="password" name="pass"></label>'
       . '<button type="submit" name="enregistrer_config" value="1">Enregistrer</button></form></body></html>';
    exit;
}
if ($erreur !== '') { echo '<p class="no">✗ Connexion impossible</p><pre>' . h($erreur) . '</pre></body></html>'; exit; }

// Explorateur (?t=)
if (isset($_GET['t']) && $_GET['t'] !== '') {
    $sel = $_GET['t']; $tables = array();
    foreach ($pdo->query('SHOW TABLES') as $r) { $v = array_values($r); $tables[] = $v[0]; }
    if (in_array($sel, $tables, true)) {
        $safe = str_replace('`', '', $sel);
        echo '<p><a href="?">← Retour</a></p><h2>' . h($sel) . '</h2><table><tr><th>Colonne</th><th>Type</th><th>Clé</th></tr>';
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $safe . '`') as $c) {
            echo '<tr><td><strong>' . h($c['Field']) . '</strong></td><td>' . h($c['Type']) . '</td><td>' . h($c['Key']) . '</td></tr>';
        }
        echo '</table>';
    }
    echo '</body></html>'; exit;
}

// ── Apporteur(s) REYNARD + marque blanche ─────────────────────────
$stmt = $pdo->prepare("SELECT id, nom, prenom, societe, commentaire, proprietaire FROM jl_app
                       WHERE (nom LIKE :q OR prenom LIKE :q OR societe LIKE :q) AND status <> 'B' ORDER BY nom");
$stmt->execute(array(':q' => '%' . $APPORTEUR . '%'));
$apporteurs = $stmt->fetchAll();
if (!$apporteurs) {
    echo '<p class="no">Aucun apporteur « ' . h($APPORTEUR) . ' » dans jl_app.</p></body></html>'; exit;
}
$ids = array(); $apMap = array(); $societes = array();
foreach ($apporteurs as $a) {
    $ids[] = (int) $a['id'];
    $com = explode('|', (string) $a['commentaire']);
    $soc = ($a['societe'] !== '' && $a['societe'] !== null) ? $a['societe'] : trim($a['nom'] . ' ' . $a['prenom']);
    $apMap[(int) $a['id']] = array(
        'mb'      => (isset($com[2]) && $com[2] == '1') ? 1 : 0, // rétro marque blanche
        'societe' => $soc,
    );
    if (!in_array($soc, $societes, true)) { $societes[] = $soc; }
}
echo '<p class="muted">Sociétés : <strong>' . h(implode(' · ', $societes)) . '</strong></p>';

// Vue courante (BI)
$vue = isset($_GET['vue']) ? $_GET['vue'] : 'bordereau';
if (!in_array($vue, array('bordereau', 'production', 'devis', 'renouvellements'), true)) { $vue = 'bordereau'; }
$annee = (isset($_GET['annee']) && preg_match('/^\d{4}$/', $_GET['annee'])) ? (int) $_GET['annee'] : (int) date('Y');

// Filtres : etat, tri, periode, societe
$etatLabels = array('P' => 'Payé', 'C' => 'En cours', 'N' => 'Non réglé', 'R' => 'Remboursé', 'A' => 'Annulé');
$etat_filtre = isset($_GET['etat_filtre']) ? $_GET['etat_filtre'] : 'TOUS';
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'date_desc';
$soc_filtre = isset($_GET['soc']) ? $_GET['soc'] : 'TOUTES';
// Par défaut : période du mois en cours (1er → dernier jour du mois)
$date_deb = validDate(isset($_GET['date_deb']) ? $_GET['date_deb'] : '', date('Y-m-01'));
$date_fin = validDate(isset($_GET['date_fin']) ? $_GET['date_fin'] : '', date('Y-m-t'));

// Restreindre aux apporteurs de la société choisie
$idsUsed = $ids;
if ($soc_filtre !== 'TOUTES') {
    $idsUsed = array();
    foreach ($apMap as $aid => $info) {
        if ($info['societe'] === $soc_filtre) { $idsUsed[] = $aid; }
    }
}

// Navigation entre tableaux de bord (BI)
echo '<div class="nav">'
   . '<a href="?vue=bordereau"' . ($vue === 'bordereau' ? ' class="on"' : '') . '>Bordereau rétrocession</a>'
   . '<a href="?vue=production"' . ($vue === 'production' ? ' class="on"' : '') . '>Production</a>'
   . '<a href="?vue=devis"' . ($vue === 'devis' ? ' class="on"' : '') . '>Devis</a>'
   . '<a href="?vue=renouvellements"' . ($vue === 'renouvellements' ? ' class="on"' : '') . '>Renouvellements</a>'
   . '</div>';

// Formulaire de filtres commun (période + société), + état/tri pour le bordereau
$labelPeriode = ($vue === 'renouvellements') ? 'Échéance entre' : 'Période';
?>
<form class="filtres" method="get">
    <input type="hidden" name="vue" value="<?php echo h($vue); ?>">
    <?php if ($vue === 'production'): ?>
        Année :
        <select name="annee">
            <?php for ($y = (int) date('Y'); $y >= 2020; $y--): ?>
                <option value="<?php echo $y; ?>"<?php echo $annee === $y ? ' selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    <?php else: ?>
        <?php echo $labelPeriode; ?> : <input type="date" name="date_deb" value="<?php echo h($date_deb); ?>">
        → <input type="date" name="date_fin" value="<?php echo h($date_fin); ?>">
    <?php endif; ?>
    &nbsp; Société :
    <select name="soc">
        <option value="TOUTES"<?php echo $soc_filtre === 'TOUTES' ? ' selected' : ''; ?>>Toutes</option>
        <?php foreach ($societes as $s): ?>
            <option value="<?php echo h($s); ?>"<?php echo $soc_filtre === $s ? ' selected' : ''; ?>><?php echo h($s); ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($vue === 'bordereau'): ?>
    <br>
    État :
    <select name="etat_filtre">
        <option value="TOUS"<?php echo $etat_filtre === 'TOUS' ? ' selected' : ''; ?>>Tous</option>
        <?php foreach ($etatLabels as $k => $lib): ?>
            <option value="<?php echo $k; ?>"<?php echo $etat_filtre === $k ? ' selected' : ''; ?>><?php echo $k . ' — ' . $lib; ?></option>
        <?php endforeach; ?>
    </select>
    &nbsp; Trier par :
    <select name="tri">
        <option value="date_desc"<?php echo $tri === 'date_desc' ? ' selected' : ''; ?>>Date (récent → ancien)</option>
        <option value="date_asc"<?php echo $tri === 'date_asc' ? ' selected' : ''; ?>>Date (ancien → récent)</option>
        <option value="societe"<?php echo $tri === 'societe' ? ' selected' : ''; ?>>Société</option>
    </select>
    <?php endif; ?>
    <button type="submit">Appliquer</button>
</form>
<?php

// =================================================================
//  VUE PRODUCTION MENSUELLE
// =================================================================
if ($vue === 'production') {
    $anneePrec = $annee - 1;
    if (!$idsUsed) {
        $rows = array();
    } else {
        $in = implode(',', array_fill(0, count($idsUsed), '?'));
        $sql = "SELECT g.id AS gid, g.id_app, g.date_demande, g.prix_assitance, g.prix_pj, g.id_lb2,
                       r.note3, r.pa, r.marge AS r_marge, r.honoraire AS r_hono, r.etat, v.categorie AS categorie
                FROM jl_garantie g
                JOIN jl_reglement r ON r.id_garantie = g.id
                LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
                WHERE g.id_app IN ($in) AND g.num_contrat <> '' AND YEAR(g.date_demande) IN (?, ?)";
        $st = $pdo->prepare($sql);
        $params = $idsUsed; $params[] = $annee; $params[] = $anneePrec;
        $st->execute($params);
        $rows = $st->fetchAll();
    }

    // Total à rétrocéder par mois (même calcul que le bordereau) + nb de contrats distincts
    $mN = array(); $mP = array(); $gN = array(); $gP = array();
    for ($i = 1; $i <= 12; $i++) { $mN[$i] = 0; $mP[$i] = 0; $gN[$i] = array(); $gP[$i] = array(); }
    foreach ($rows as $r) {
        $ts = strtotime($r['date_demande']); if (!$ts) { continue; }
        $y = (int) date('Y', $ts); $m = (int) date('n', $ts);
        $mb = isset($apMap[(int) $r['id_app']]) ? $apMap[(int) $r['id_app']]['mb'] : 0;
        $etat = $r['etat'];
        $montant = num($r['note3']); if ($etat === 'A' || $etat === 'R') { $montant = 0; }
        $prime_esc = num($r['pa']); $pv = $prime_esc + num($r['r_marge']) + num($r['r_hono']);
        $prix_ass = num($r['prix_assitance']); $prix_dr = num($r['prix_pj']);
        if ($mb == 1) { $base = catCam($r['categorie']) ? ($pv - 1) : $pv; $rcdr = round(($base - $prime_esc) * 0.4764, 2); $hono = round(num($r['id_lb2']), 2); }
        else          { $rcdr = round($montant - $pv - $prix_ass - $prix_dr, 2); $hono = 0; }
        if ($rcdr <= 0) { $rcdr = 0; } if ($hono <= 0) { $hono = 0; }
        $retro = $rcdr + $hono;
        if ($y === $annee)         { $mN[$m] += $retro; $gN[$m][(int) $r['gid']] = 1; }
        elseif ($y === $anneePrec) { $mP[$m] += $retro; $gP[$m][(int) $r['gid']] = 1; }
    }
    $totRetroN = 0; $totRetroP = 0; $totNbN = 0; $totNbP = 0;
    for ($i = 1; $i <= 12; $i++) { $totRetroN += $mN[$i]; $totRetroP += $mP[$i]; $totNbN += count($gN[$i]); $totNbP += count($gP[$i]); }
    $evol = ($totRetroP > 0) ? round(($totRetroN - $totRetroP) / $totRetroP * 100, 1) : null;

    $moisLbl = array('Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc');
    $retroN = array(); $retroP = array(); $nbN = array(); $nbP = array();
    for ($i = 1; $i <= 12; $i++) { $retroN[] = round($mN[$i], 2); $retroP[] = round($mP[$i], 2); $nbN[] = count($gN[$i]); $nbP[] = count($gP[$i]); }

    if ($EXPORT === 'csv') {
        $csv = array(array('Mois', 'Contrats ' . $annee, 'A retroceder ' . $annee, 'Contrats ' . $anneePrec, 'A retroceder ' . $anneePrec));
        for ($i = 1; $i <= 12; $i++) { $csv[] = array($moisLbl[$i - 1], count($gN[$i]), round($mN[$i], 2), count($gP[$i]), round($mP[$i], 2)); }
        $csv[] = array('Total', $totNbN, round($totRetroN, 2), $totNbP, round($totRetroP, 2));
        csvOut('production_' . $annee . '.csv', $csv);
    }
    ?>
    <p class="muted">Production <?php echo h($LABEL); ?> — année <strong><?php echo $annee; ?></strong> (comparée à <?php echo $anneePrec; ?>), basée sur la date de souscription. Montants = total à rétrocéder (commissions RC DR + honoraires).</p>
    <div class="stats">
        <div class="stat"><b><?php echo $totNbN; ?></b><span>Contrats <?php echo $annee; ?></span></div>
        <div class="stat hl"><b><?php echo euros($totRetroN); ?></b><span>À rétrocéder <?php echo $annee; ?></span></div>
        <div class="stat"><b><?php echo euros($totRetroP); ?></b><span>À rétrocéder <?php echo $anneePrec; ?></span></div>
        <div class="stat"><b style="color:<?php echo ($evol !== null && $evol < 0) ? '#c02b2b' : '#1a7d49'; ?>"><?php echo $evol === null ? '—' : ($evol > 0 ? '+' : '') . $evol . ' %'; ?></b><span>Évolution vs <?php echo $anneePrec; ?></span></div>
    </div>

    <p><a href="<?php echo h(lienCsv()); ?>" style="display:inline-block;background:#1a7d49;color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none">⬇ Exporter en CSV</a></p>

    <div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:16px">
        <div style="flex:1 1 420px;min-width:320px;background:#fff;border:1px solid #e3e8f0;border-radius:8px;padding:14px">
            <strong>Total à rétrocéder par mois</strong>
            <div style="height:300px;margin-top:8px"><canvas id="retroChart"></canvas></div>
        </div>
        <div style="flex:1 1 420px;min-width:320px;background:#fff;border:1px solid #e3e8f0;border-radius:8px;padding:14px">
            <strong>Nombre de contrats par mois</strong>
            <div style="height:300px;margin-top:8px"><canvas id="nbChart"></canvas></div>
        </div>
    </div>

    <div style="overflow:auto;margin-top:20px">
    <table>
        <thead><tr>
            <th>Mois</th>
            <th class="num">Contrats <?php echo $annee; ?></th><th class="num">À rétrocéder <?php echo $annee; ?></th>
            <th class="num">Contrats <?php echo $anneePrec; ?></th><th class="num">À rétrocéder <?php echo $anneePrec; ?></th>
        </tr></thead>
        <tbody>
        <?php for ($i = 1; $i <= 12; $i++): ?>
            <tr>
                <td><?php echo $moisLbl[$i - 1]; ?></td>
                <td class="num"><?php echo count($gN[$i]); ?></td>
                <td class="num"><?php echo euros($mN[$i]); ?></td>
                <td class="num"><?php echo count($gP[$i]); ?></td>
                <td class="num"><?php echo euros($mP[$i]); ?></td>
            </tr>
        <?php endfor; ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#eef4ff">
            <td>Total</td>
            <td class="num"><?php echo $totNbN; ?></td>
            <td class="num"><?php echo euros($totRetroN); ?></td>
            <td class="num"><?php echo $totNbP; ?></td>
            <td class="num"><?php echo euros($totRetroP); ?></td>
        </tr></tfoot>
    </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        if (typeof Chart === 'undefined') { return; }
        var labels = <?php echo json_encode($moisLbl, JSON_UNESCAPED_UNICODE); ?>;
        var retroN = <?php echo json_encode($retroN); ?>, retroP = <?php echo json_encode($retroP); ?>;
        var nbN = <?php echo json_encode($nbN); ?>, nbP = <?php echo json_encode($nbP); ?>;
        var blue = '#1f5eff', gray = '#c9d3e6';
        new Chart(document.getElementById('retroChart'), {
            type: 'bar',
            data: { labels: labels, datasets: [
                { label: '<?php echo $annee; ?>', data: retroN, backgroundColor: blue },
                { label: '<?php echo $anneePrec; ?>', data: retroP, backgroundColor: gray }
            ]},
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { tooltip: { callbacks: { label: function (c) { return c.dataset.label + ' : ' + c.parsed.y.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €'; } } } },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return v.toLocaleString('fr-FR') + ' €'; } } } } }
        });
        new Chart(document.getElementById('nbChart'), {
            type: 'line',
            data: { labels: labels, datasets: [
                { label: '<?php echo $annee; ?>', data: nbN, borderColor: blue, backgroundColor: blue, tension: 0.3 },
                { label: '<?php echo $anneePrec; ?>', data: nbP, borderColor: gray, backgroundColor: gray, tension: 0.3 }
            ]},
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    })();
    </script>
    </body></html>
    <?php
    exit;
}

// =================================================================
//  VUE DEVIS (demandes non transformées en contrat)
// =================================================================
if ($vue === 'devis') {
    if (!$idsUsed) {
        $rows = array();
    } else {
        $in = implode(',', array_fill(0, count($idsUsed), '?'));
        $sql = "SELECT g.id, g.id_app, g.num_garantie, g.type_contrat, g.formule, g.date_demande, g.date_effet, g.prix_formule,
                       cl.nom AS cnom, cl.prenom AS cprenom, cl.ville AS cville, cl.mobile AS cmobile, cl.mail AS cmail,
                       v.immatriculation AS immat, v.marque AS marque, v.modele AS modele
                FROM jl_garantie g
                LEFT JOIN jl_client cl ON cl.id = g.id_cli
                LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
                WHERE g.id_app IN ($in) AND (g.num_contrat = '' OR g.num_contrat IS NULL)
                      AND DATE(g.date_demande) BETWEEN ? AND ?
                ORDER BY g.date_demande DESC, g.id DESC";
        $st = $pdo->prepare($sql);
        $params = $idsUsed; $params[] = $date_deb; $params[] = $date_fin;
        $st->execute($params);
        $rows = $st->fetchAll();
    }

    $aujourdhui = strtotime(date('Y-m-d'));
    $totPrime = 0;
    foreach ($rows as $r) { $totPrime += num($r['prix_formule']); }

    // URL JLASSURE (modifiable dans db.ini : jlassure_url = "...garantie_modif.php?id={id}")
    $jlUrlTpl = (isset($cfg['jlassure_url']) && $cfg['jlassure_url'] !== '')
        ? $cfg['jlassure_url']
        : 'https://www.jlassure.com/sousfiche/gestion/garantie_modif.php?id={id}';

    if ($EXPORT === 'csv') {
        $csv = array(array('Date', 'N devis', 'Societe', 'Client', 'Ville', 'Mobile', 'Mail', 'Immat', 'Vehicule', 'Produit', 'Prime', 'Anciennete (j)', 'Lien JLASSURE'));
        foreach ($rows as $r) {
            $age = floor(($aujourdhui - strtotime($r['date_demande'])) / 86400);
            $soc = isset($apMap[(int) $r['id_app']]) ? $apMap[(int) $r['id_app']]['societe'] : '';
            $csv[] = array(dateFr($r['date_demande']), $r['num_garantie'], $soc, trim($r['cnom'] . ' ' . $r['cprenom']), $r['cville'],
                $r['cmobile'], $r['cmail'], $r['immat'], trim($r['marque'] . ' ' . $r['modele']),
                trim($r['type_contrat'] . ' ' . $r['formule']), num($r['prix_formule']), $age,
                str_replace('{id}', (int) $r['id'], $jlUrlTpl));
        }
        csvOut('devis_' . $date_deb . '_' . $date_fin . '.csv', $csv);
    }
    ?>
    <p class="muted">Devis <?php echo h($LABEL); ?> (demandes sans n° de contrat) du
       <?php echo dateFr($date_deb); ?> au <?php echo dateFr($date_fin); ?>.</p>
    <div class="stats">
        <div class="stat"><b><?php echo count($rows); ?></b><span>Devis en attente</span></div>
        <div class="stat"><b><?php echo euros($totPrime); ?></b><span>Primes potentielles</span></div>
    </div>
    <p><a href="<?php echo h(lienCsv()); ?>" style="display:inline-block;background:#1a7d49;color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none">⬇ Exporter en CSV</a></p>

    <?php if (!$rows): ?>
        <p class="muted">Aucun devis en attente sur cette période.</p>
    <?php else: ?>
    <div style="overflow:auto">
    <table>
        <thead><tr>
            <th>Date</th><th>N° devis</th><th>Société</th><th>Client</th><th>Ville</th><th>Contact</th>
            <th>Véhicule</th><th>Produit</th><th class="num">Prime</th><th class="ctr">Ancienneté</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $veh = trim($r['marque'] . ' ' . $r['modele']);
            $age = floor(($aujourdhui - strtotime($r['date_demande'])) / 86400);
            $bg = ($age > 7) ? '#fff2d9' : '#fff';
            $soc = isset($apMap[(int) $r['id_app']]) ? $apMap[(int) $r['id_app']]['societe'] : '';
            $lienJl = str_replace('{id}', (int) $r['id'], $jlUrlTpl);
        ?>
            <tr style="background:<?php echo $bg; ?>">
                <td><?php echo dateFr($r['date_demande']); ?></td>
                <td><a href="<?php echo h($lienJl); ?>" target="_blank" rel="noopener" title="Ouvrir dans JLASSURE"><?php echo h($r['num_garantie']); ?> ↗</a></td>
                <td><?php echo h($soc); ?></td>
                <td><?php echo h(trim($r['cnom'] . ' ' . $r['cprenom'])); ?></td>
                <td><?php echo h($r['cville']); ?></td>
                <td><?php echo h($r['cmobile']); ?><?php echo ($r['cmail'] !== '' && $r['cmail'] !== null) ? '<br><span class="muted">' . h($r['cmail']) . '</span>' : ''; ?></td>
                <td><?php echo h($r['immat']); ?><?php echo $veh !== '' ? ' <span class="muted">' . h($veh) . '</span>' : ''; ?></td>
                <td><?php echo h($r['type_contrat'] . ' ' . $r['formule']); ?></td>
                <td class="num"><?php echo euros(num($r['prix_formule'])); ?></td>
                <td class="ctr"><?php echo (int) $age; ?> j</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <p class="tools">Devis = garanties sans <code>num_contrat</code>. <a href="?t=jl_garantie">jl_garantie</a></p>
    </body></html>
    <?php
    exit;
}

// =================================================================
//  VUE RENOUVELLEMENTS
// =================================================================
if ($vue === 'renouvellements') {
    if (!$idsUsed) {
        $rows = array();
    } else {
        $in = implode(',', array_fill(0, count($idsUsed), '?'));
        // Jointure sur le règlement principal (g.id_reglement) pour connaître l'état.
        // On exclut les contrats annulés (etat = 'A').
        $sql = 'SELECT g.id AS gid, g.id_app, g.id_vehi, g.id_cli, g.num_contrat, g.type_contrat, g.formule, g.duree,
                       g.date_effet, g.date_fin, g.date_demande, g.prix_formule, r.etat AS etat,
                       cl.nom AS client_nom, cl.prenom AS client_prenom, cl.ville AS client_ville,
                       cl.telephone AS client_tel, cl.mobile AS client_mobile, cl.mail AS client_mail,
                       cl.adresse AS client_adresse, cl.code_postal AS client_cp,
                       v.immatriculation, v.marque, v.modele
                FROM jl_garantie g
                LEFT JOIN jl_client cl ON cl.id = g.id_cli
                LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
                LEFT JOIN jl_reglement r ON r.id = g.id_reglement
                WHERE g.id_app IN (' . $in . ") AND g.num_contrat <> ''
                      AND (r.etat IS NULL OR r.etat <> 'A')
                      AND DATE(g.date_demande) BETWEEN ? AND ?
                ORDER BY g.id_vehi, g.date_effet";
        $st = $pdo->prepare($sql);
        $params = $idsUsed; $params[] = $date_deb; $params[] = $date_fin;
        $st->execute($params);
        $rows = $st->fetchAll();
    }

    // Regroupement par VÉHICULE (renouvellement même véhicule)
    $groupes = array();
    // Regroupement par CLIENT (fidélité, véhicule différent ou non)
    $parClient = array();
    foreach ($rows as $r) {
        $soc = isset($apMap[(int) $r['id_app']]) ? $apMap[(int) $r['id_app']]['societe'] : '';

        // — par véhicule —
        $k = (int) $r['id_vehi'];
        if ($k > 0) {
            if (!isset($groupes[$k])) {
                $groupes[$k] = array(
                    'id_vehi' => $k, 'immat' => $r['immatriculation'],
                    'vehicule' => trim($r['marque'] . ' ' . $r['modele']),
                    'client' => trim($r['client_nom'] . ' ' . $r['client_prenom']),
                    'ville' => $r['client_ville'], 'societe' => $soc,
                    'contrats' => array(), 'total' => 0,
                );
            }
            $groupes[$k]['contrats'][] = $r;
            $groupes[$k]['total'] += num($r['prix_formule']);
        }

        // — par client —
        $cid = (int) $r['id_cli'];
        if ($cid > 0) {
            if (!isset($parClient[$cid])) {
                $parClient[$cid] = array(
                    'client' => trim($r['client_nom'] . ' ' . $r['client_prenom']),
                    'ville' => $r['client_ville'], 'societe' => $soc,
                    'tel' => $r['client_tel'], 'mobile' => $r['client_mobile'], 'mail' => $r['client_mail'],
                    'adresse' => trim($r['client_adresse'] . ' ' . $r['client_cp'] . ' ' . $r['client_ville']),
                    'nb' => 0, 'vehs' => array(), 'total' => 0, 'contrats' => array(),
                );
            }
            $parClient[$cid]['nb']++;
            if ($k > 0) { $parClient[$cid]['vehs'][$k] = 1; }
            $parClient[$cid]['total'] += num($r['prix_formule']);
            $parClient[$cid]['contrats'][] = $r;
        }
    }

    $multi = array();
    foreach ($groupes as $g) { if (count($g['contrats']) >= 2) { $multi[] = $g; } }
    usort($multi, function ($a, $b) { return count($b['contrats']) - count($a['contrats']); });

    $fideles = array();
    foreach ($parClient as $c) { if ($c['nb'] >= 2) { $fideles[] = $c; } }
    usort($fideles, function ($a, $b) { return $b['nb'] - $a['nb']; });

    $nbVeh = count($multi); $nbContrats = 0; $totPrime = 0;
    foreach ($multi as $g) { $nbContrats += count($g['contrats']); $totPrime += $g['total']; }

    if ($EXPORT === 'vehicules') {
        $csv = array(array('Immat', 'Vehicule', 'Client', 'Ville', 'Societe', 'Nb contrats vehicule', 'N contrat', 'Type', 'Formule', 'Date effet', 'Echeance', 'Duree', 'Prime'));
        foreach ($multi as $g) {
            foreach ($g['contrats'] as $ct) {
                $csv[] = array($g['immat'], $g['vehicule'], $g['client'], $g['ville'], $g['societe'], count($g['contrats']),
                    $ct['num_contrat'], $ct['type_contrat'], $ct['formule'], dateFr($ct['date_effet']), dateFr($ct['date_fin']), $ct['duree'], num($ct['prix_formule']));
            }
        }
        csvOut('renouvellements_vehicules.csv', $csv);
    }
    if ($EXPORT === 'csv') {
        $csv = array(array('Client', 'Ville', 'Societe', 'Nb contrats', 'Nb vehicules', 'Primes cumulees', 'Telephone', 'Mobile', 'Mail'));
        foreach ($fideles as $c) {
            $csv[] = array($c['client'], $c['ville'], $c['societe'], $c['nb'], count($c['vehs']), round($c['total'], 2), $c['tel'], $c['mobile'], $c['mail']);
        }
        csvOut('clients_fideles.csv', $csv);
    }
    // Sous-vue choisie (bascule) : même véhicule OU par nombre de contrats
    $sous = (isset($_GET['sous']) && $_GET['sous'] === 'clients') ? 'clients' : 'vehicule';
    $baseQ = $_GET; unset($baseQ['export']);
    $lienVeh = '?' . http_build_query(array_merge($baseQ, array('vue' => 'renouvellements', 'sous' => 'vehicule')));
    $lienCli = '?' . http_build_query(array_merge($baseQ, array('vue' => 'renouvellements', 'sous' => 'clients')));
    ?>
    <p class="muted"><?php echo h($LABEL); ?> — souscriptions du <?php echo dateFr($date_deb); ?> au <?php echo dateFr($date_fin); ?> (contrats annulés exclus).</p>
    <div class="stats">
        <div class="stat"><b><?php echo $nbVeh; ?></b><span>Véhicules renouvelés</span></div>
        <div class="stat"><b><?php echo count($fideles); ?></b><span>Clients fidèles</span></div>
        <div class="stat"><b><?php echo $nbContrats; ?></b><span>Contrats (même véhicule)</span></div>
        <div class="stat"><b><?php echo euros($totPrime); ?></b><span>Primes cumulées (véhicule)</span></div>
    </div>

    <div style="display:inline-flex;border:1px solid #ccd4e2;border-radius:8px;overflow:hidden;margin:6px 0 4px;font-weight:600">
        <a href="<?php echo h($lienVeh); ?>" style="padding:9px 18px;text-decoration:none;<?php echo $sous === 'vehicule' ? 'background:#1f5eff;color:#fff' : 'color:#1f5eff;background:#fff'; ?>">🚗 Même véhicule (<?php echo $nbVeh; ?>)</a>
        <a href="<?php echo h($lienCli); ?>" style="padding:9px 18px;text-decoration:none;border-left:1px solid #ccd4e2;<?php echo $sous === 'clients' ? 'background:#1f5eff;color:#fff' : 'color:#1f5eff;background:#fff'; ?>">👥 Par nombre de contrats (<?php echo count($fideles); ?>)</a>
    </div>

    <?php if ($sous === 'vehicule'): ?>
    <h2>Renouvellements — même véhicule</h2>
    <p><a href="<?php echo h(lienCsv(array('export' => 'vehicules'))); ?>" style="display:inline-block;background:#1a7d49;color:#fff;padding:7px 13px;border-radius:6px;text-decoration:none;font-size:14px">⬇ Exporter (véhicules)</a></p>
    <?php if (!$multi): ?>
        <p class="muted">Aucun véhicule avec plusieurs contrats sur cette période.
        Élargis la période (ex. 01/01/2026 → 31/12/2026).</p>
    <?php else: ?>
    <div style="overflow:auto">
    <table>
        <thead><tr>
            <th>N° contrat</th><th>Type</th><th>Formule</th><th>Date effet</th><th>Échéance</th><th class="ctr">Durée</th><th class="num">Prime</th>
        </tr></thead>
        <tbody>
        <?php foreach ($multi as $g): ?>
            <tr style="background:#eef4ff">
                <td colspan="7" style="padding:8px 10px">
                    🚗 <strong><?php echo h($g['immat'] !== '' && $g['immat'] !== null ? $g['immat'] : 'Véhicule #' . $g['id_vehi']); ?></strong>
                    <?php echo $g['vehicule'] !== '' ? '— ' . h($g['vehicule']) : ''; ?>
                    &nbsp;|&nbsp; Client : <strong><?php echo h($g['client']); ?></strong>
                    <?php echo $g['ville'] !== '' ? '(' . h($g['ville']) . ')' : ''; ?>
                    <?php echo $g['societe'] !== '' ? '&nbsp;|&nbsp; ' . h($g['societe']) : ''; ?>
                    &nbsp;|&nbsp; <strong style="color:#1f5eff"><?php echo count($g['contrats']); ?> contrats</strong>
                    &nbsp;|&nbsp; Total <?php echo euros($g['total']); ?>
                </td>
            </tr>
            <?php foreach ($g['contrats'] as $c): ?>
            <tr>
                <td><?php echo h($c['num_contrat']); ?></td>
                <td><?php echo h($c['type_contrat']); ?></td>
                <td><?php echo h($c['formule']); ?></td>
                <td><?php echo dateFr($c['date_effet']); ?></td>
                <td><?php echo dateFr($c['date_fin']); ?></td>
                <td class="ctr"><?php echo h($c['duree']); ?></td>
                <td class="num"><?php echo euros(num($c['prix_formule'])); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    <?php endif; /* fin sous=vehicule */ ?>

    <?php if ($sous === 'clients'): ?>
    <h2>Clients fidèles — plusieurs contrats (véhicule différent ou non)</h2>
    <p><a href="<?php echo h(lienCsv()); ?>" style="display:inline-block;background:#1a7d49;color:#fff;padding:7px 13px;border-radius:6px;text-decoration:none;font-size:14px">⬇ Exporter (clients fidèles)</a></p>
    <?php if (!$fideles): ?>
        <p class="muted">Aucun client avec plusieurs contrats sur cette période.</p>
    <?php else: ?>
    <p class="muted" style="margin-top:0">Clique sur un client pour afficher ses coordonnées et ses contrats.</p>
    <div style="overflow:auto">
    <table>
        <thead><tr>
            <th>Client</th><th>Ville</th><th>Société</th>
            <th class="ctr">Nb contrats</th><th class="ctr">Nb véhicules</th><th class="num">Primes cumulées</th>
        </tr></thead>
        <tbody>
        <?php foreach ($fideles as $i => $c): ?>
            <tr onclick="toggleCli(<?php echo $i; ?>)" style="cursor:pointer">
                <td>▸ <strong><?php echo h($c['client']); ?></strong></td>
                <td><?php echo h($c['ville']); ?></td>
                <td><?php echo h($c['societe']); ?></td>
                <td class="ctr"><strong style="color:#1f5eff"><?php echo $c['nb']; ?></strong></td>
                <td class="ctr"><?php echo count($c['vehs']); ?></td>
                <td class="num"><?php echo euros($c['total']); ?></td>
            </tr>
            <tr id="cli-<?php echo $i; ?>" style="display:none">
                <td colspan="6" style="background:#f9fbff;padding:12px 16px">
                    <div style="margin-bottom:10px">
                        <strong>Coordonnées</strong> &nbsp;|&nbsp;
                        📞 <?php echo $c['tel'] !== '' && $c['tel'] !== null ? '<a href="tel:' . h($c['tel']) . '">' . h($c['tel']) . '</a>' : '<span class="muted">—</span>'; ?>
                        &nbsp; 📱 <?php echo $c['mobile'] !== '' && $c['mobile'] !== null ? '<a href="tel:' . h($c['mobile']) . '">' . h($c['mobile']) . '</a>' : '<span class="muted">—</span>'; ?>
                        &nbsp; ✉️ <?php echo $c['mail'] !== '' && $c['mail'] !== null ? '<a href="mailto:' . h($c['mail']) . '">' . h($c['mail']) . '</a>' : '<span class="muted">—</span>'; ?>
                        <?php echo $c['adresse'] !== '' ? '<br>🏠 ' . h($c['adresse']) : ''; ?>
                    </div>
                    <table style="margin-top:4px">
                        <thead><tr><th>Date</th><th>N° contrat</th><th>Véhicule</th><th>Produit</th><th class="num">Prime</th></tr></thead>
                        <tbody>
                        <?php foreach ($c['contrats'] as $ct):
                            $veh = trim($ct['marque'] . ' ' . $ct['modele']); ?>
                            <tr>
                                <td><?php echo dateFr($ct['date_demande']); ?></td>
                                <td><?php echo h($ct['num_contrat']); ?></td>
                                <td><?php echo h($ct['immatriculation']); ?><?php echo $veh !== '' ? ' <span class="muted">' . h($veh) . '</span>' : ''; ?></td>
                                <td><?php echo h($ct['type_contrat'] . ' ' . $ct['formule']); ?></td>
                                <td class="num"><?php echo euros(num($ct['prix_formule'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <script>
    function toggleCli(i){ var e=document.getElementById('cli-'+i); if(e){ e.style.display = (e.style.display==='none') ? '' : 'none'; } }
    </script>
    <?php endif; ?>
    <?php endif; /* fin sous=clients */ ?>

    <p class="tools">Contrat annulé = règlement principal en état « A ». <a href="?t=jl_garantie">jl_garantie</a> · <a href="?t=jl_vehicule">jl_vehicule</a></p>
    </body></html>
    <?php
    exit;
}

// =================================================================
//  VUE BORDEREAU (par défaut)
// =================================================================

// ── Garanties (contrats) sur la période + reglements ──────────────
if (!$idsUsed) {
    $brut = array();
} else {
    $in = implode(',', array_fill(0, count($idsUsed), '?'));
    $sql = 'SELECT g.id AS gid, g.id_app, g.num_contrat, g.num_garantie, g.duree, g.date_demande,
                   g.prix_achat AS g_pa, g.marge AS g_marge, g.honoraire AS g_hono, g.id_lb2,
                   g.prix_assitance, g.prix_pj,
                   r.id AS rid, r.date_reglement, r.note3, r.etat, r.note2, r.pa, r.marge AS r_marge, r.honoraire AS r_hono,
                   cl.nom AS client_nom, cl.prenom AS client_prenom, v.categorie AS categorie
            FROM jl_garantie g
            JOIN jl_reglement r ON r.id_garantie = g.id
            LEFT JOIN jl_client cl ON cl.id = g.id_cli
            LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
            WHERE g.id_app IN (' . $in . ") AND g.num_contrat <> '' AND DATE(g.date_demande) BETWEEN ? AND ?
            ORDER BY r.date_reglement DESC, g.id DESC";
    $st = $pdo->prepare($sql);
    $params = $idsUsed;
    $params[] = $date_deb;
    $params[] = $date_fin;
    $st->execute($params);
    $brut = $st->fetchAll();
}

// Calculs par ligne
$lignes = array(); $totBanque = 0; $totRcdr = 0; $totHono = 0;
foreach ($brut as $rw) {
    $etat = $rw['etat'];
    if ($etat_filtre !== 'TOUS' && $etat !== $etat_filtre) { continue; }

    $idApp   = (int) $rw['id_app'];
    $mb      = isset($apMap[$idApp]) ? $apMap[$idApp]['mb'] : 0;
    $societe = isset($apMap[$idApp]) ? $apMap[$idApp]['societe'] : '';

    $montant_regle = num($rw['note3']);
    $prime_esc     = num($rw['pa']);
    $pv            = $prime_esc + num($rw['r_marge']) + num($rw['r_hono']);
    $prix_ass      = num($rw['prix_assitance']);
    $prix_dr       = num($rw['prix_pj']);
    $pv_client     = num($rw['g_pa']) + num($rw['g_marge']) + num($rw['g_hono']) + num($rw['id_lb2']) + $prix_ass + $prix_dr;

    if ($etat === 'A' || $etat === 'R') { $montant_regle = 0; $pv_client = 0; }

    // Rétro RC DR (commissions) + Rétro Honoraires (retro_sup = id_lb2 en marque blanche)
    if ($mb == 1) {
        $base = catCam($rw['categorie']) ? ($pv - 1) : $pv;   // règle "1 € camion" (TCP/CAM/REM/TRA)
        $retro_rcdr = round(($base - $prime_esc) * 0.4764, 2);
        $retro_hono = round(num($rw['id_lb2']), 2);
    } else {
        $retro_rcdr = round($montant_regle - $pv - $prix_ass - $prix_dr, 2);
        $retro_hono = 0;
    }
    if ($retro_rcdr <= 0) { $retro_rcdr = 0; }
    if ($retro_hono <= 0) { $retro_hono = 0; }

    $totBanque += $montant_regle;
    $totRcdr   += $retro_rcdr;
    $totHono   += $retro_hono;

    $lignes[] = array('etat' => $etat, 'gid' => $rw['gid'], 'rid' => $rw['rid'],
        'dr' => $rw['date_reglement'], 'dd' => $rw['date_demande'], 'nc' => $rw['num_contrat'],
        'duree' => $rw['duree'], 'client' => trim($rw['client_nom'] . ' ' . $rw['client_prenom']),
        'societe' => $societe, 'banque' => $montant_regle, 'pvc' => $pv_client,
        'rcdr' => $retro_rcdr, 'hono' => $retro_hono, 'info' => $rw['note2']);
}
$totRetro = $totRcdr + $totHono;

// Tri
if ($tri === 'date_asc') {
    usort($lignes, function ($a, $b) { return strtotime($a['dr']) - strtotime($b['dr']); });
} elseif ($tri === 'societe') {
    usort($lignes, function ($a, $b) {
        $c = strcasecmp($a['societe'], $b['societe']);
        return $c !== 0 ? $c : (strtotime($b['dr']) - strtotime($a['dr']));
    });
} else { // date_desc
    usort($lignes, function ($a, $b) { return strtotime($b['dr']) - strtotime($a['dr']); });
}

if ($EXPORT === 'csv') {
    $csv = array(array('Etat', 'ID Gar', 'ID Reg', 'Regle le', 'Demande', 'N contrat', 'Duree', 'Client', 'Societe', 'Banque', 'PV client', 'Retro RC DR', 'Retro Honoraires', 'Total retro', 'Info'));
    foreach ($lignes as $l) {
        $csv[] = array($l['etat'], $l['gid'], $l['rid'], dateFr($l['dr']), dateFr($l['dd']), $l['nc'], $l['duree'], $l['client'], $l['societe'],
            $l['banque'], $l['pvc'], $l['rcdr'], $l['hono'], $l['rcdr'] + $l['hono'], $l['info']);
    }
    csvOut('bordereau_' . $date_deb . '_' . $date_fin . '.csv', $csv);
}
?>

<div class="stats">
    <div class="stat"><b><?php echo count($lignes); ?></b><span>Lignes (règlements)</span></div>
    <div class="stat"><b><?php echo euros($totBanque); ?></b><span>Total réglé (banque)</span></div>
    <div class="stat"><b><?php echo euros($totRcdr); ?></b><span>Rétro RC DR (commissions)</span></div>
    <div class="stat"><b><?php echo euros($totHono); ?></b><span>Rétro Honoraires</span></div>
    <div class="stat hl"><b><?php echo euros($totRetro); ?></b><span>Total à rétrocéder à <?php echo h($LABEL); ?></span></div>
</div>

<p><a href="<?php echo h(lienCsv()); ?>" style="display:inline-block;background:#1a7d49;color:#fff;padding:8px 14px;border-radius:6px;text-decoration:none">⬇ Exporter en CSV</a></p>
<div class="legende">
    <span style="background:#FFF0F0">En cours (C)</span>
    <span style="background:#FFD0F0">Annulé / autre</span>
    <span style="background:#fff;border:1px solid #ddd">Payé (P)</span>
</div>

<?php if (!$lignes): ?>
    <p class="muted">Aucun contrat pour <?php echo $soc_filtre === 'TOUTES' ? 'cet apporteur' : h($soc_filtre); ?>
    du <?php echo dateFr($date_deb); ?> au <?php echo dateFr($date_fin); ?><?php echo $etat_filtre !== 'TOUS' ? ' (état ' . h($etat_filtre) . ')' : ''; ?>.</p>
<?php else: ?>
<div style="overflow:auto">
<table>
    <thead><tr>
        <th>État</th><th>ID Gar</th><th>ID Rég</th><th>Réglé le</th><th>Demande</th>
        <th>N° contrat</th><th>Durée</th><th>Client</th><th>Société</th>
        <th class="num">Banque</th><th class="num">PV client</th>
        <th class="num">Rétro RC DR</th><th class="num">Rétro Honoraires</th><th class="num">Total rétro</th><th>Info</th>
    </tr></thead>
    <tbody>
    <?php foreach ($lignes as $l):
        $bg = ($l['etat'] === 'P') ? '#ffffff' : (($l['etat'] === 'C') ? '#FFF0F0' : '#FFD0F0');
        $mt = ($l['banque'] <= 0) ? '#ffdede' : $bg;
        $ligneTot = $l['rcdr'] + $l['hono'];
        $fw = ($ligneTot > 0) ? 'font-weight:bold;' : '';
    ?>
        <tr style="background:<?php echo $bg; ?>">
            <td class="ctr"><strong><?php echo h($l['etat']); ?></strong> <span class="muted"><?php echo isset($etatLabels[$l['etat']]) ? h($etatLabels[$l['etat']]) : ''; ?></span></td>
            <td class="ctr"><?php echo (int) $l['gid']; ?></td>
            <td class="ctr"><?php echo (int) $l['rid']; ?></td>
            <td><?php echo dateFr($l['dr']); ?></td>
            <td><?php echo dateFr($l['dd']); ?></td>
            <td><?php echo h($l['nc']); ?></td>
            <td class="ctr"><?php echo h($l['duree']); ?></td>
            <td><?php echo h($l['client']); ?></td>
            <td><?php echo h($l['societe']); ?></td>
            <td class="num" style="background:<?php echo $mt; ?>"><?php echo euros($l['banque']); ?></td>
            <td class="num"><?php echo euros($l['pvc']); ?></td>
            <td class="num"><?php echo euros($l['rcdr']); ?></td>
            <td class="num"><?php echo euros($l['hono']); ?></td>
            <td class="num" style="<?php echo $fw; ?>"><?php echo euros($ligneTot); ?></td>
            <td><?php echo h($l['info']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr style="font-weight:bold;background:#eef4ff">
        <td colspan="9" style="text-align:right">TOTAUX</td>
        <td class="num"><?php echo euros($totBanque); ?></td>
        <td></td>
        <td class="num"><?php echo euros($totRcdr); ?></td>
        <td class="num"><?php echo euros($totHono); ?></td>
        <td class="num"><?php echo euros($totRetro); ?></td>
        <td></td>
    </tr></tfoot>
</table>
</div>
<?php endif; ?>

<p class="tools">Outil : <a href="?t=jl_garantie">jl_garantie</a> · <a href="?t=jl_reglement">jl_reglement</a> · <a href="?t=jl_app">jl_app</a></p>

</body></html>
