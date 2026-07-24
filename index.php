<?php
// ===================================================================
//  MCJ-Courtage — Portail sécurisé + Contrats REYNARD (source JLASSURE)
//  Fichier unique. Secrets dans db.ini et auth.ini (bloqués en HTTP : 403).
// ===================================================================
error_reporting(E_ALL);
ini_set('display_errors', '1'); // TODO: passer a '0' quand tout est stabilise
ini_set('session.cookie_httponly', '1');
session_start();
header('Content-Type: text/html; charset=utf-8');

$APPORTEUR = 'REYNARD';
$DIR      = dirname(__FILE__);
$dbFile   = $DIR . '/db.ini';
$authFile = $DIR . '/auth.ini';

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function euros($v) {
    if ($v === null || $v === '') { return '—'; }
    return number_format((float) str_replace(array(' ', ','), array('', '.'), $v), 2, ',', ' ') . ' €';
}
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
    return 'body{font-family:system-ui,Arial,sans-serif;max-width:1200px;margin:0 auto;padding:0 16px 40px;line-height:1.55;color:#222;background:#f4f6fb}'
      . 'h1{color:#1f5eff;margin-bottom:2px} h2{margin-top:26px;border-bottom:1px solid #e3e8f0;padding-bottom:6px}'
      . 'code{background:#eef;padding:2px 6px;border-radius:4px} pre{background:#f6f8fc;border:1px solid #dde3ee;padding:12px;border-radius:8px;overflow:auto;white-space:pre-wrap}'
      . '.ok{color:#1a7d49;font-weight:bold} .no{color:#c02b2b;font-weight:bold} .muted{color:#888}'
      . 'table{border-collapse:collapse;width:100%;margin-top:10px;background:#fff}'
      . 'th,td{border:1px solid #e3e8f0;padding:7px 10px;text-align:left;font-size:14px;white-space:nowrap}'
      . 'th{background:#f7f9fd} tbody tr:hover{background:#f9fbff} .num{text-align:right}'
      . '.box{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-top:20px}'
      . '.stats{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0}'
      . '.stat{background:#fff;border:1px solid #e3e8f0;border-radius:8px;padding:12px 18px;min-width:140px}'
      . '.stat b{display:block;font-size:22px;color:#1f5eff} .stat span{color:#666;font-size:13px}'
      . '.topbar{display:flex;justify-content:space-between;align-items:center;background:#fff;border-bottom:1px solid #e3e8f0;padding:12px 18px;margin:0 -16px 20px}'
      . '.topbar .brand{font-weight:700;color:#1f5eff;font-size:18px} .topbar .right{font-size:14px;color:#555}'
      . '.topbar a{color:#c02b2b;text-decoration:none;margin-left:14px}'
      . '.card{max-width:380px;margin:60px auto;background:#fff;border:1px solid #dde3ee;border-radius:10px;padding:28px;box-shadow:0 6px 24px rgba(20,30,60,.06)}'
      . '.card h1{margin-top:0} .card label{display:block;margin-bottom:14px;font-weight:600}'
      . '.card input{width:100%;padding:10px 12px;border:1px solid #ccd4e2;border-radius:6px;font:inherit;font-weight:400;box-sizing:border-box}'
      . '.card button{width:100%;background:#1f5eff;color:#fff;border:0;padding:11px;border-radius:6px;font:inherit;font-weight:600;cursor:pointer}'
      . '.alert{padding:10px 14px;border-radius:6px;margin-bottom:16px} .alert.err{background:#fde3e3;color:#a12} .alert.okk{background:#dcf5e8;color:#1a7d49}'
      . 'form.cfg{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:20px;max-width:420px}'
      . 'form.cfg label{display:block;margin-bottom:14px;font-weight:600} form.cfg input{width:100%;padding:9px 11px;border:1px solid #ccd4e2;border-radius:6px;font:inherit;font-weight:400;box-sizing:border-box}'
      . 'form.cfg button{background:#1f5eff;color:#fff;border:0;padding:10px 18px;border-radius:6px;font:inherit;cursor:pointer}'
      . 'a{color:#1f5eff} .tools{font-size:13px;color:#888;margin-top:30px}';
}

// ── Deconnexion ───────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ── Etat du compte administrateur ─────────────────────────────────
$auth = is_file($authFile) ? parse_ini_file($authFile) : null;
$authUser = ($auth && isset($auth['user'])) ? $auth['user'] : '';
$authHash = ($auth && isset($auth['hash'])) ? base64_decode($auth['hash']) : '';

$erreurAuth = '';

// ── Creation du compte (premiere visite, si auth.ini absent) ──────
if ($auth === null && isset($_POST['creer_compte'])) {
    if (!jetonOk()) {
        $erreurAuth = 'Session expirée, réessaie.';
    } else {
        $u  = trim(isset($_POST['user']) ? $_POST['user'] : '');
        $p  = isset($_POST['pass']) ? $_POST['pass'] : '';
        $p2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';
        if ($u === '' || strlen($p) < 6) {
            $erreurAuth = 'Identifiant requis et mot de passe d\'au moins 6 caractères.';
        } elseif ($p !== $p2) {
            $erreurAuth = 'Les deux mots de passe ne correspondent pas.';
        } else {
            $contenu = 'user = "' . str_replace('"', '', $u) . '"' . "\n"
                     . 'hash = "' . base64_encode(password_hash($p, PASSWORD_DEFAULT)) . '"' . "\n";
            if (@file_put_contents($authFile, $contenu) !== false) {
                @chmod($authFile, 0600);
                session_regenerate_id(true);
                $_SESSION['user'] = $u;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            $erreurAuth = 'Impossible d\'écrire auth.ini (permissions du dossier).';
        }
    }
}

// ── Connexion ─────────────────────────────────────────────────────
if ($auth !== null && isset($_POST['connexion'])) {
    if (!jetonOk()) {
        $erreurAuth = 'Session expirée, réessaie.';
    } else {
        $u = trim(isset($_POST['user']) ? $_POST['user'] : '');
        $p = isset($_POST['pass']) ? $_POST['pass'] : '';
        if (hash_equals($authUser, $u) && password_verify($p, $authHash)) {
            session_regenerate_id(true);
            $_SESSION['user'] = $authUser;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $erreurAuth = 'Identifiant ou mot de passe incorrect.';
    }
}

$connecte = !empty($_SESSION['user']);

// ================================================================
//  ECRAN 1 — Création du compte admin (aucun compte défini)
// ================================================================
if ($auth === null) {
    entete('MCJ-Courtage — Créer le compte');
    echo '<div class="card"><h1>Premier accès</h1>'
       . '<p class="muted">Crée le compte administrateur qui protégera l\'application.</p>';
    if ($erreurAuth) { echo '<div class="alert err">' . h($erreurAuth) . '</div>'; }
    echo '<form method="post">'
       . '<input type="hidden" name="csrf" value="' . h(jeton()) . '">'
       . '<label>Identifiant<input type="text" name="user" required autofocus></label>'
       . '<label>Mot de passe (6 caractères min.)<input type="password" name="pass" required></label>'
       . '<label>Confirmer le mot de passe<input type="password" name="pass2" required></label>'
       . '<button type="submit" name="creer_compte" value="1">Créer le compte</button>'
       . '</form></div></body></html>';
    exit;
}

// ================================================================
//  ECRAN 2 — Connexion (compte défini, pas encore connecté)
// ================================================================
if (!$connecte) {
    entete('MCJ-Courtage — Connexion');
    echo '<div class="card"><h1>Connexion</h1>';
    if ($erreurAuth) { echo '<div class="alert err">' . h($erreurAuth) . '</div>'; }
    echo '<form method="post">'
       . '<input type="hidden" name="csrf" value="' . h(jeton()) . '">'
       . '<label>Identifiant<input type="text" name="user" required autofocus></label>'
       . '<label>Mot de passe<input type="password" name="pass" required></label>'
       . '<button type="submit" name="connexion" value="1">Se connecter</button>'
       . '</form></div></body></html>';
    exit;
}

// ================================================================
//  ZONE PROTEGEE — utilisateur connecté
// ================================================================
entete('MCJ-Courtage — Contrats ' . $APPORTEUR);
echo '<div class="topbar"><span class="brand">MCJ-Courtage</span>'
   . '<span class="right">👤 ' . h($_SESSION['user'])
   . ' <a href="?logout=1">Déconnexion</a></span></div>';

// ── Enregistrement de la config base (formulaire) ─────────────────
$messageEcriture = '';
if (isset($_POST['enregistrer_config'])) {
    if (!jetonOk()) {
        $messageEcriture = 'Session expirée, réessaie.';
    } else {
        $contenu = 'host = "' . str_replace('"', '', trim($_POST['host'])) . '"' . "\n"
                 . 'base = "' . str_replace('"', '', trim($_POST['base'])) . '"' . "\n"
                 . 'user = "' . str_replace('"', '', trim($_POST['user'])) . '"' . "\n"
                 . 'pass = "' . str_replace('"', '', $_POST['pass']) . '"' . "\n";
        if (@file_put_contents($dbFile, $contenu) !== false) {
            @chmod($dbFile, 0600);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $messageEcriture = "Impossible d'écrire db.ini.";
    }
}

$cfg = is_file($dbFile) ? parse_ini_file($dbFile) : null;
$pdo = null; $erreur = '';
if ($cfg !== null) {
    try {
        $pdo = new PDO(
            'mysql:host=' . (isset($cfg['host']) ? $cfg['host'] : 'localhost')
            . ';dbname=' . (isset($cfg['base']) ? $cfg['base'] : '') . ';charset=utf8',
            isset($cfg['user']) ? $cfg['user'] : '', isset($cfg['pass']) ? $cfg['pass'] : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $e) { $erreur = $e->getMessage(); }
}

echo '<h1>Contrats de l\'apporteur <span style="color:#1f5eff">' . h($APPORTEUR) . '</span></h1>'
   . '<p class="muted">Source JLASSURE — PHP ' . phpversion() . '</p>';

if ($cfg === null) {
    if ($messageEcriture) { echo '<div class="alert err">' . h($messageEcriture) . '</div>'; }
    echo '<h2>Configuration de la base JLASSURE</h2>'
       . '<form class="cfg" method="post"><input type="hidden" name="csrf" value="' . h(jeton()) . '">'
       . '<label>Serveur<input type="text" name="host" value="localhost" required></label>'
       . '<label>Nom de la base JLASSURE<input type="text" name="base" required></label>'
       . '<label>Utilisateur MySQL<input type="text" name="user" required></label>'
       . '<label>Mot de passe MySQL<input type="password" name="pass"></label>'
       . '<button type="submit" name="enregistrer_config" value="1">Enregistrer et connecter</button></form>';
    echo '</body></html>'; exit;
}

if ($erreur !== '') {
    echo '<p class="no">✗ Connexion à la base impossible</p><pre>' . h($erreur) . '</pre>';
    echo '</body></html>'; exit;
}

// ── Explorateur de tables (outil : ?t=nom) ────────────────────────
if (isset($_GET['t']) && $_GET['t'] !== '') {
    $sel = $_GET['t']; $tables = array();
    foreach ($pdo->query('SHOW TABLES') as $row) { $v = array_values($row); $tables[] = $v[0]; }
    if (in_array($sel, $tables, true)) {
        $safe = str_replace('`', '', $sel);
        echo '<p><a href="?">← Retour</a></p><h2>Colonnes de ' . h($sel) . '</h2><table><tr><th>Colonne</th><th>Type</th><th>Clé</th></tr>';
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $safe . '`') as $c) {
            echo '<tr><td><strong>' . h($c['Field']) . '</strong></td><td>' . h($c['Type']) . '</td><td>' . h($c['Key']) . '</td></tr>';
        }
        echo '</table>';
    }
    echo '</body></html>'; exit;
}

// ── Apporteur REYNARD ─────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, nom, prenom, societe FROM jl_app
                       WHERE nom LIKE :q OR prenom LIKE :q OR societe LIKE :q ORDER BY nom');
$stmt->execute(array(':q' => '%' . $APPORTEUR . '%'));
$apporteurs = $stmt->fetchAll();

if (!$apporteurs) {
    echo '<p class="no">Aucun apporteur « ' . h($APPORTEUR) . ' » dans jl_app.</p>';
    echo '</body></html>'; exit;
}

$ids = array();
foreach ($apporteurs as $a) { $ids[] = (int) $a['id']; }
$in = implode(',', array_fill(0, count($ids), '?'));
$sql = 'SELECT g.id, g.num_contrat, g.num_garantie, g.type_contrat, g.formule,
               g.date_effet, g.date_fin, g.prix_formule, g.com_app, g.status,
               cl.nom AS client_nom, cl.prenom AS client_prenom, cl.ville AS client_ville,
               co.compagnie AS compagnie
        FROM jl_garantie g
        LEFT JOIN jl_client cl ON g.id_cli = cl.id
        LEFT JOIN jl_compagnie co ON g.id_comp = co.id
        WHERE g.id_app IN (' . $in . ')
        ORDER BY g.date_effet DESC, g.id DESC';
$st = $pdo->prepare($sql);
$st->execute($ids);
$contrats = $st->fetchAll();

$nb = count($contrats); $totPrime = 0; $totCom = 0;
foreach ($contrats as $c) {
    $totPrime += (float) str_replace(array(' ', ','), array('', '.'), $c['prix_formule']);
    $totCom   += (float) str_replace(array(' ', ','), array('', '.'), $c['com_app']);
}
?>

<div class="stats">
    <div class="stat"><b><?php echo $nb; ?></b><span>Contrats</span></div>
    <div class="stat"><b><?php echo euros($totPrime); ?></b><span>Primes cumulées</span></div>
    <div class="stat"><b><?php echo euros($totCom); ?></b><span>Commissions apporteur</span></div>
</div>

<?php if (!$contrats): ?>
    <p class="muted">Aucun contrat trouvé pour cet apporteur.</p>
<?php else: ?>
<div style="overflow:auto">
<table>
    <thead><tr>
        <th>N° contrat</th><th>Client</th><th>Ville</th><th>Compagnie</th>
        <th>Type</th><th>Formule</th><th>Date effet</th><th>Date fin</th>
        <th class="num">Prime</th><th class="num">Com. app.</th><th>Statut</th>
    </tr></thead>
    <tbody>
    <?php foreach ($contrats as $c): ?>
        <tr>
            <td><?php echo h($c['num_contrat'] !== '' && $c['num_contrat'] !== null ? $c['num_contrat'] : $c['num_garantie']); ?></td>
            <td><?php echo h(trim($c['client_nom'] . ' ' . $c['client_prenom'])); ?></td>
            <td><?php echo h($c['client_ville']); ?></td>
            <td><?php echo h($c['compagnie']); ?></td>
            <td><?php echo h($c['type_contrat']); ?></td>
            <td><?php echo h($c['formule']); ?></td>
            <td><?php echo dateFr($c['date_effet']); ?></td>
            <td><?php echo dateFr($c['date_fin']); ?></td>
            <td class="num"><?php echo euros($c['prix_formule']); ?></td>
            <td class="num"><?php echo euros($c['com_app']); ?></td>
            <td><?php echo h($c['status']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<p class="tools">Outil : <a href="?t=jl_garantie">jl_garantie</a> · <a href="?t=jl_app">jl_app</a></p>

</body></html>
