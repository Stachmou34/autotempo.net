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

$APPORTEUR = 'REYNARD';
$DEPUIS    = '2026-01-01';   // n'afficher que les contrats depuis 2026
$DIR       = dirname(__FILE__);
$dbFile    = $DIR . '/db.ini';
$authFile  = $DIR . '/auth.ini';

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function num($v) { return (float) str_replace(array(' ', ','), array('', '.'), (string) $v); }
function euros($v) { return number_format((float) $v, 2, ',', ' ') . ' €'; }
function dateFr($v) {
    if (empty($v) || $v === '0000-00-00') { return '—'; }
    $ts = strtotime($v);
    return $ts ? date('d/m/Y', $ts) : h($v);
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
    return 'body{font-family:system-ui,Arial,sans-serif;max-width:1400px;margin:0 auto;padding:0 16px 40px;line-height:1.5;color:#222;background:#f4f6fb}'
      . 'h1{color:#1f5eff;margin-bottom:2px} h2{margin-top:24px;border-bottom:1px solid #e3e8f0;padding-bottom:6px}'
      . 'code{background:#eef;padding:2px 6px;border-radius:4px} pre{background:#f6f8fc;border:1px solid #dde3ee;padding:12px;border-radius:8px;overflow:auto;white-space:pre-wrap}'
      . '.ok{color:#1a7d49;font-weight:bold} .no{color:#c02b2b;font-weight:bold} .muted{color:#888}'
      . 'table{border-collapse:collapse;width:100%;margin-top:10px;background:#fff;font-size:13px}'
      . 'th,td{border:1px solid #e3e8f0;padding:6px 9px;text-align:left;white-space:nowrap}'
      . 'th{background:#f7f9fd} .num{text-align:right} .ctr{text-align:center}'
      . '.box{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-top:20px}'
      . '.stats{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0}'
      . '.stat{background:#fff;border:1px solid #e3e8f0;border-radius:8px;padding:12px 18px;min-width:150px}'
      . '.stat b{display:block;font-size:22px;color:#1f5eff} .stat span{color:#666;font-size:13px}'
      . '.stat.hl b{color:#1a7d49}'
      . '.topbar{display:flex;justify-content:space-between;align-items:center;background:#fff;border-bottom:1px solid #e3e8f0;padding:12px 18px;margin:0 -16px 20px}'
      . '.topbar .brand{font-weight:700;color:#1f5eff;font-size:18px} .topbar .right{font-size:14px;color:#555}'
      . '.topbar a{color:#c02b2b;text-decoration:none;margin-left:14px}'
      . '.filtres{margin:14px 0;font-size:14px} .filtres select,.filtres button{font:inherit;padding:6px 10px;border:1px solid #ccd4e2;border-radius:6px}'
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
entete('MCJ-Courtage — Bordereau ' . $APPORTEUR);
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

echo '<h1>Bordereau rétrocession — apporteur <span style="color:#1f5eff">' . h($APPORTEUR) . '</span></h1>'
   . '<p class="muted">Source JLASSURE — contrats depuis le ' . dateFr($DEPUIS) . '</p>';

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
$stmt = $pdo->prepare('SELECT id, nom, prenom, societe, commentaire, proprietaire FROM jl_app
                       WHERE nom LIKE :q OR prenom LIKE :q OR societe LIKE :q ORDER BY nom');
$stmt->execute(array(':q' => '%' . $APPORTEUR . '%'));
$apporteurs = $stmt->fetchAll();
if (!$apporteurs) {
    echo '<p class="no">Aucun apporteur « ' . h($APPORTEUR) . ' » dans jl_app.</p></body></html>'; exit;
}
$ids = array(); $apMap = array(); $noms = array();
foreach ($apporteurs as $a) {
    $ids[] = (int) $a['id'];
    $com = explode('|', (string) $a['commentaire']);
    $apMap[(int) $a['id']] = (isset($com[2]) && $com[2] == '1') ? 1 : 0; // retro marque blanche
    $noms[] = $a['nom'] . ' ' . $a['prenom'] . ($a['societe'] ? ' (' . $a['societe'] . ')' : '');
}
echo '<p class="muted">Apporteur : <strong>' . h(implode(' · ', $noms)) . '</strong></p>';

// Filtre d'etat
$etatLabels = array('P' => 'Payé', 'C' => 'En cours', 'N' => 'Non réglé', 'R' => 'Remboursé', 'A' => 'Annulé');
$etat_filtre = isset($_GET['etat_filtre']) ? $_GET['etat_filtre'] : 'TOUS';

// ── Garanties (contrats) depuis 2026 + reglements ─────────────────
$in = implode(',', array_fill(0, count($ids), '?'));
$sql = 'SELECT g.id AS gid, g.id_app, g.num_contrat, g.num_garantie, g.duree, g.date_demande,
               g.prix_achat AS g_pa, g.marge AS g_marge, g.honoraire AS g_hono, g.id_lb2,
               g.prix_assitance, g.prix_pj,
               r.id AS rid, r.date_reglement, r.note3, r.etat, r.note2, r.pa, r.marge AS r_marge, r.honoraire AS r_hono,
               cl.nom AS client_nom, cl.prenom AS client_prenom
        FROM jl_garantie g
        JOIN jl_reglement r ON r.id_garantie = g.id
        LEFT JOIN jl_client cl ON cl.id = g.id_cli
        WHERE g.id_app IN (' . $in . ") AND g.num_contrat <> '' AND g.date_demande >= '" . $DEPUIS . "'
        ORDER BY r.date_reglement DESC, g.id DESC";
$st = $pdo->prepare($sql);
$st->execute($ids);
$brut = $st->fetchAll();

// Calculs par ligne
$lignes = array(); $totBanque = 0; $totRetro = 0;
foreach ($brut as $rw) {
    $etat = $rw['etat'];
    if ($etat_filtre !== 'TOUS' && $etat !== $etat_filtre) { continue; }

    $montant_regle = num($rw['note3']);
    $prime_esc     = num($rw['pa']);
    $pv            = $prime_esc + num($rw['r_marge']) + num($rw['r_hono']);
    $prix_ass      = num($rw['prix_assitance']);
    $prix_dr       = num($rw['prix_pj']);
    $pv_client     = num($rw['g_pa']) + num($rw['g_marge']) + num($rw['g_hono']) + num($rw['id_lb2']) + $prix_ass + $prix_dr;

    if ($etat === 'A' || $etat === 'R') { $montant_regle = 0; $pv_client = 0; }

    $mb = isset($apMap[(int) $rw['id_app']]) ? $apMap[(int) $rw['id_app']] : 0;
    if ($mb == 1) {
        $retro = round(($pv - $prime_esc) * 0.4764, 2);   // marque blanche
    } else {
        $retro = round($montant_regle - $pv - $prix_ass - $prix_dr, 2);
    }
    if ($retro <= 0) { $retro = 0; }

    $totBanque += $montant_regle;
    $totRetro  += $retro;

    $lignes[] = array('etat' => $etat, 'gid' => $rw['gid'], 'rid' => $rw['rid'],
        'dr' => $rw['date_reglement'], 'dd' => $rw['date_demande'], 'nc' => $rw['num_contrat'],
        'duree' => $rw['duree'], 'client' => trim($rw['client_nom'] . ' ' . $rw['client_prenom']),
        'banque' => $montant_regle, 'pvc' => $pv_client, 'retro' => $retro, 'info' => $rw['note2']);
}
?>

<div class="stats">
    <div class="stat"><b><?php echo count($lignes); ?></b><span>Lignes (règlements)</span></div>
    <div class="stat"><b><?php echo euros($totBanque); ?></b><span>Total réglé (banque)</span></div>
    <div class="stat hl"><b><?php echo euros($totRetro); ?></b><span>Total à rétrocéder à <?php echo h($APPORTEUR); ?></span></div>
</div>

<form class="filtres" method="get">
    Filtrer par état :
    <select name="etat_filtre" onchange="this.form.submit()">
        <option value="TOUS"<?php echo $etat_filtre === 'TOUS' ? ' selected' : ''; ?>>Tous</option>
        <?php foreach ($etatLabels as $k => $lib): ?>
            <option value="<?php echo $k; ?>"<?php echo $etat_filtre === $k ? ' selected' : ''; ?>><?php echo $k . ' — ' . $lib; ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Appliquer</button>
    <div class="legende">
        <span style="background:#FFF0F0">En cours (C)</span>
        <span style="background:#FFD0F0">Annulé / autre</span>
        <span style="background:#fff;border:1px solid #ddd">Payé (P)</span>
    </div>
</form>

<?php if (!$lignes): ?>
    <p class="muted">Aucun contrat pour cet apporteur depuis <?php echo dateFr($DEPUIS); ?><?php echo $etat_filtre !== 'TOUS' ? ' (état ' . h($etat_filtre) . ')' : ''; ?>.</p>
<?php else: ?>
<div style="overflow:auto">
<table>
    <thead><tr>
        <th>État</th><th>ID Gar</th><th>ID Rég</th><th>Réglé le</th><th>Demande</th>
        <th>N° contrat</th><th>Durée</th><th>Client</th>
        <th class="num">Banque</th><th class="num">PV client</th><th class="num">Rétro apporteur</th><th>Info</th>
    </tr></thead>
    <tbody>
    <?php foreach ($lignes as $l):
        $bg = ($l['etat'] === 'P') ? '#ffffff' : (($l['etat'] === 'C') ? '#FFF0F0' : '#FFD0F0');
        $mt = ($l['banque'] <= 0) ? '#ffdede' : $bg;
        $fw = ($l['retro'] > 0) ? 'font-weight:bold;' : '';
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
            <td class="num" style="background:<?php echo $mt; ?>"><?php echo euros($l['banque']); ?></td>
            <td class="num"><?php echo euros($l['pvc']); ?></td>
            <td class="num" style="<?php echo $fw; ?>"><?php echo euros($l['retro']); ?></td>
            <td><?php echo h($l['info']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr style="font-weight:bold;background:#eef4ff">
        <td colspan="8" style="text-align:right">TOTAUX</td>
        <td class="num"><?php echo euros($totBanque); ?></td>
        <td></td>
        <td class="num"><?php echo euros($totRetro); ?></td>
        <td></td>
    </tr></tfoot>
</table>
</div>
<?php endif; ?>

<p class="tools">Outil : <a href="?t=jl_garantie">jl_garantie</a> · <a href="?t=jl_reglement">jl_reglement</a> · <a href="?t=jl_app">jl_app</a></p>

</body></html>
