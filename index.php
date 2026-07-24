<?php
// ===================================================================
//  MCJ-Courtage — fichier unique, sans dependance.
//  Identifiants a mettre dans un fichier "db.ini" a cote de ce fichier :
//
//      host = localhost
//      base = nom_de_la_base_jlassure
//      user = utilisateur
//      pass = motdepasse
//
// ===================================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$iniFile = dirname(__FILE__) . '/db.ini';
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
</style></head>
<body>

<h1>MCJ-Courtage</h1>
<p>PHP <?php echo phpversion(); ?> — <?php echo date('d/m/Y H:i'); ?></p>

<?php if ($cfg === null): ?>

    <h2>Configuration</h2>
    <p class="no">Le fichier <code>db.ini</code> n'existe pas encore.</p>
    <div class="box">
        Crée-le à côté de <code>index.php</code> avec ce contenu :
        <pre>host = localhost
base = NOM_DE_LA_BASE_JLASSURE
user = UTILISATEUR
pass = MOTDEPASSE</pre>
        puis recharge cette page.
    </div>

<?php elseif ($erreur !== ''): ?>

    <h2>Connexion</h2>
    <p class="no">✗ Connexion impossible</p>
    <pre><?php echo h($erreur); ?></pre>
    <div class="box">Corrige <code>db.ini</code> (nom de la base, utilisateur, mot de passe).</div>

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
