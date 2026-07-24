<?php
// ===================================================================
//  MCJ-Courtage — fichier unique, sans dependance.
//  Au premier lancement, la page propose un formulaire qui cree
//  automatiquement le fichier db.ini avec les identifiants saisis.
// ===================================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$iniFile = dirname(__FILE__) . '/db.ini';
$messageEcriture = '';

// ── Enregistrement du formulaire de configuration ─────────────────
if (isset($_POST['enregistrer_config'])) {
    $host = isset($_POST['host']) ? trim($_POST['host']) : 'localhost';
    $base = isset($_POST['base']) ? trim($_POST['base']) : '';
    $user = isset($_POST['user']) ? trim($_POST['user']) : '';
    $pass = isset($_POST['pass']) ? $_POST['pass'] : '';

    // Guillemets pour que les caracteres speciaux du mot de passe passent.
    $contenu = "host = \"" . str_replace('"', '', $host) . "\"\n"
             . "base = \"" . str_replace('"', '', $base) . "\"\n"
             . "user = \"" . str_replace('"', '', $user) . "\"\n"
             . "pass = \"" . str_replace('"', '', $pass) . "\"\n";

    if (@file_put_contents($iniFile, $contenu) !== false) {
        @chmod($iniFile, 0600);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $messageEcriture = "Impossible d'ecrire le fichier db.ini (permissions). "
                     . "Cree-le a la main avec ce contenu :\n\n" . $contenu;
}

$cfg = is_file($iniFile) ? parse_ini_file($iniFile) : null;

$pdo = null;
$erreur = '';
if ($cfg !== null) {
    $host = isset($cfg['host']) ? $cfg['host'] : 'localhost';
    $base = isset($cfg['base']) ? $cfg['base'] : '';
    $user = isset($cfg['user']) ? $cfg['user'] : '';
    $pass = isset($cfg['pass']) ? $cfg['pass'] : '';
    try {
        $pdo = new PDO('mysql:host=' . $host . ';dbname=' . $base . ';charset=utf8', $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erreur = $e->getMessage();
    }
}
?><!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>MCJ-Courtage</title>
<style>
body{font-family:system-ui,Arial,sans-serif;max-width:1000px;margin:36px auto;padding:0 16px;line-height:1.6;color:#222}
h1{color:#1f5eff;margin-bottom:4px} h2{margin-top:28px;border-bottom:1px solid #e3e8f0;padding-bottom:6px}
code{background:#eef;padding:2px 6px;border-radius:4px}
pre{background:#f6f8fc;border:1px solid #dde3ee;padding:12px;border-radius:8px;overflow:auto;white-space:pre-wrap}
.ok{color:#1a7d49;font-weight:bold} .no{color:#c02b2b;font-weight:bold}
table{border-collapse:collapse;width:100%;margin-top:10px}
th,td{border:1px solid #e3e8f0;padding:6px 10px;text-align:left;font-size:14px}
th{background:#f7f9fd}
.box{background:#f6f8fc;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-top:20px}
a{color:#1f5eff} .tags a{display:inline-block;margin:2px 6px 2px 0}
form.cfg{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:20px;max-width:420px}
form.cfg label{display:block;margin-bottom:14px;font-weight:600}
form.cfg input{width:100%;padding:9px 11px;border:1px solid #ccd4e2;border-radius:6px;font:inherit;font-weight:400}
form.cfg button{background:#1f5eff;color:#fff;border:0;padding:10px 18px;border-radius:6px;font:inherit;cursor:pointer}
</style></head>
<body>

<h1>MCJ-Courtage</h1>
<p>PHP <?php echo phpversion(); ?> — <?php echo date('d/m/Y H:i'); ?></p>

<?php if ($messageEcriture !== ''): ?>
    <h2>Configuration</h2>
    <p class="no">Écriture impossible</p>
    <pre><?php echo h($messageEcriture); ?></pre>

<?php elseif ($cfg === null): ?>

    <h2>Configuration de la base JLASSURE</h2>
    <p>Remplis les champs : le fichier <code>db.ini</code> sera créé automatiquement.</p>
    <form class="cfg" method="post">
        <label>Serveur
            <input type="text" name="host" value="localhost" required>
        </label>
        <label>Nom de la base JLASSURE
            <input type="text" name="base" placeholder="ex : jlassure" required autofocus>
        </label>
        <label>Utilisateur MySQL
            <input type="text" name="user" required>
        </label>
        <label>Mot de passe MySQL
            <input type="password" name="pass">
        </label>
        <button type="submit" name="enregistrer_config" value="1">Enregistrer et connecter</button>
    </form>

<?php elseif ($erreur !== ''): ?>

    <h2>Connexion</h2>
    <p class="no">✗ Connexion impossible</p>
    <pre><?php echo h($erreur); ?></pre>
    <div class="box">
        Corrige les identifiants : supprime le fichier puis recharge cette page
        pour revoir le formulaire.
        <pre>rm /home/autotemnet/public_html/db.ini</pre>
    </div>

<?php else: ?>

    <h2>Connexion</h2>
    <p class="ok">✓ Connecté à <code><?php echo h($cfg['base']); ?></code></p>

    <h2>Tables</h2>
    <?php
    $tables = array();
    $res = $pdo->query('SHOW TABLES');
    foreach ($res as $row) {
        $vals = array_values($row);
        $tables[] = $vals[0];
    }
    echo '<p>' . count($tables) . ' table(s) — clique pour voir le contenu :</p><p class="tags">';
    $sel = isset($_GET['t']) ? $_GET['t'] : '';
    foreach ($tables as $t) {
        $gras = ($t === $sel) ? 'font-weight:bold;text-decoration:underline' : '';
        echo '<a href="?t=' . urlencode($t) . '" style="' . $gras . '">' . h($t) . '</a>';
    }
    echo '</p>';

    if ($sel !== '' && in_array($sel, $tables, true)) {
        $safe = str_replace('`', '', $sel);

        echo '<h2>Colonnes de ' . h($sel) . '</h2><table><tr><th>Colonne</th><th>Type</th><th>Clé</th></tr>';
        $cols = $pdo->query('SHOW COLUMNS FROM `' . $safe . '`');
        foreach ($cols as $c) {
            echo '<tr><td><strong>' . h($c['Field']) . '</strong></td><td>' . h($c['Type']) . '</td><td>' . h($c['Key']) . '</td></tr>';
        }
        echo '</table>';

        $stmt = $pdo->query('SELECT * FROM `' . $safe . '` LIMIT 5');
        $lignes = $stmt->fetchAll();
        if ($lignes) {
            echo '<h2>Aperçu (5 lignes)</h2><div style="overflow:auto"><table><tr>';
            foreach (array_keys($lignes[0]) as $k) { echo '<th>' . h($k) . '</th>'; }
            echo '</tr>';
            foreach ($lignes as $l) {
                echo '<tr>';
                foreach ($l as $v) {
                    $v = (string) $v;
                    if (strlen($v) > 40) { $v = substr($v, 0, 39) . '…'; }
                    echo '<td>' . h($v) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table></div>';
        } else {
            echo '<p>Table vide.</p>';
        }
    } else {
        echo '<div class="box">Clique sur une table ci-dessus. Cherche celle qui contient '
           . 'les <strong>contrats</strong> et une colonne <strong>apporteur</strong> (avec REYNARD).</div>';
    }
    ?>

<?php endif; ?>

</body>
</html>
