<?php
// ===================================================================
//  MCJ-Courtage — Contrats de l'apporteur REYNARD (source JLASSURE)
//  Fichier unique, sans dependance. Identifiants dans db.ini.
// ===================================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

// Apporteur recherche (nom tel qu'enregistre dans jl_app).
$APPORTEUR = 'REYNARD';

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function euros($v) {
    if ($v === null || $v === '') { return '—'; }
    return number_format((float) str_replace(array(' ', ','), array('', '.'), $v), 2, ',', ' ') . ' €';
}
function dateFr($v) {
    if (empty($v) || $v === '0000-00-00') { return '—'; }
    $ts = strtotime($v);
    return $ts ? date('d/m/Y', $ts) : h($v);
}

$iniFile = dirname(__FILE__) . '/db.ini';
$messageEcriture = '';

// ── Enregistrement du formulaire de configuration ─────────────────
if (isset($_POST['enregistrer_config'])) {
    $host = isset($_POST['host']) ? trim($_POST['host']) : 'localhost';
    $base = isset($_POST['base']) ? trim($_POST['base']) : '';
    $user = isset($_POST['user']) ? trim($_POST['user']) : '';
    $pass = isset($_POST['pass']) ? $_POST['pass'] : '';
    $contenu = "host = \"" . str_replace('"', '', $host) . "\"\n"
             . "base = \"" . str_replace('"', '', $base) . "\"\n"
             . "user = \"" . str_replace('"', '', $user) . "\"\n"
             . "pass = \"" . str_replace('"', '', $pass) . "\"\n";
    if (@file_put_contents($iniFile, $contenu) !== false) {
        @chmod($iniFile, 0600);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $messageEcriture = "Impossible d'ecrire db.ini (permissions). Contenu a creer :\n\n" . $contenu;
}

$cfg = is_file($iniFile) ? parse_ini_file($iniFile) : null;
$pdo = null;
$erreur = '';
if ($cfg !== null) {
    try {
        $pdo = new PDO(
            'mysql:host=' . (isset($cfg['host']) ? $cfg['host'] : 'localhost')
            . ';dbname=' . (isset($cfg['base']) ? $cfg['base'] : '') . ';charset=utf8',
            isset($cfg['user']) ? $cfg['user'] : '',
            isset($cfg['pass']) ? $cfg['pass'] : ''
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erreur = $e->getMessage();
    }
}
?><!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>MCJ-Courtage — Contrats REYNARD</title>
<style>
body{font-family:system-ui,Arial,sans-serif;max-width:1200px;margin:30px auto;padding:0 16px;line-height:1.55;color:#222}
h1{color:#1f5eff;margin-bottom:2px} h2{margin-top:26px;border-bottom:1px solid #e3e8f0;padding-bottom:6px}
code{background:#eef;padding:2px 6px;border-radius:4px}
pre{background:#f6f8fc;border:1px solid #dde3ee;padding:12px;border-radius:8px;overflow:auto;white-space:pre-wrap}
.ok{color:#1a7d49;font-weight:bold} .no{color:#c02b2b;font-weight:bold} .muted{color:#888}
table{border-collapse:collapse;width:100%;margin-top:10px}
th,td{border:1px solid #e3e8f0;padding:7px 10px;text-align:left;font-size:14px;white-space:nowrap}
th{background:#f7f9fd} tbody tr:hover{background:#f9fbff}
.num{text-align:right}
.box{background:#f6f8fc;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-top:20px}
.stats{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0}
.stat{background:#fff;border:1px solid #e3e8f0;border-radius:8px;padding:12px 18px;min-width:140px}
.stat b{display:block;font-size:22px;color:#1f5eff} .stat span{color:#666;font-size:13px}
form.cfg{background:#fff;border:1px solid #dde3ee;border-radius:8px;padding:20px;max-width:420px}
form.cfg label{display:block;margin-bottom:14px;font-weight:600}
form.cfg input{width:100%;padding:9px 11px;border:1px solid #ccd4e2;border-radius:6px;font:inherit;font-weight:400}
form.cfg button{background:#1f5eff;color:#fff;border:0;padding:10px 18px;border-radius:6px;font:inherit;cursor:pointer}
.tools{font-size:13px;color:#888;margin-top:30px}
</style></head>
<body>

<h1>MCJ-Courtage</h1>
<p class="muted">Contrats de l'apporteur <strong style="color:#1f5eff"><?php echo h($APPORTEUR); ?></strong> — source JLASSURE — PHP <?php echo phpversion(); ?></p>

<?php if ($messageEcriture !== ''): ?>
    <p class="no">Écriture impossible</p><pre><?php echo h($messageEcriture); ?></pre>

<?php elseif ($cfg === null): ?>
    <h2>Configuration de la base JLASSURE</h2>
    <p>Remplis les champs : le fichier <code>db.ini</code> sera créé automatiquement.</p>
    <form class="cfg" method="post">
        <label>Serveur <input type="text" name="host" value="localhost" required></label>
        <label>Nom de la base JLASSURE <input type="text" name="base" required autofocus></label>
        <label>Utilisateur MySQL <input type="text" name="user" required></label>
        <label>Mot de passe MySQL <input type="password" name="pass"></label>
        <button type="submit" name="enregistrer_config" value="1">Enregistrer et connecter</button>
    </form>

<?php elseif ($erreur !== ''): ?>
    <p class="no">✗ Connexion impossible</p><pre><?php echo h($erreur); ?></pre>
    <div class="box">Pour ressaisir les identifiants : <pre>rm <?php echo h(dirname(__FILE__)); ?>/db.ini</pre></div>

<?php else: ?>

    <?php
    // ── Explorateur de tables (outil, optionnel : ?t=nom_table) ────
    if (isset($_GET['t']) && $_GET['t'] !== '') {
        $sel = $_GET['t'];
        $tables = array();
        foreach ($pdo->query('SHOW TABLES') as $row) { $v = array_values($row); $tables[] = $v[0]; }
        if (in_array($sel, $tables, true)) {
            $safe = str_replace('`', '', $sel);
            echo '<p><a href="?">← Retour aux contrats</a></p><h2>Colonnes de ' . h($sel) . '</h2><table><tr><th>Colonne</th><th>Type</th><th>Clé</th></tr>';
            foreach ($pdo->query('SHOW COLUMNS FROM `' . $safe . '`') as $c) {
                echo '<tr><td><strong>' . h($c['Field']) . '</strong></td><td>' . h($c['Type']) . '</td><td>' . h($c['Key']) . '</td></tr>';
            }
            echo '</table>';
            $ap = $pdo->query('SELECT * FROM `' . $safe . '` LIMIT 5')->fetchAll();
            if ($ap) {
                echo '<h2>Aperçu</h2><div style="overflow:auto"><table><tr>';
                foreach (array_keys($ap[0]) as $k) { echo '<th>' . h($k) . '</th>'; }
                echo '</tr>';
                foreach ($ap as $l) { echo '<tr>'; foreach ($l as $vv) { $vv=(string)$vv; if(strlen($vv)>40)$vv=substr($vv,0,39).'…'; echo '<td>'.h($vv).'</td>'; } echo '</tr>'; }
                echo '</table></div>';
            }
        }
        echo '</body></html>';
        exit;
    }

    // ── Contrats de l'apporteur REYNARD ───────────────────────────
    // 1) Retrouver le(s) apporteur(s) correspondant.
    $stmt = $pdo->prepare('SELECT id, nom, prenom, societe FROM jl_app
                           WHERE nom LIKE :q OR prenom LIKE :q OR societe LIKE :q ORDER BY nom');
    $stmt->execute(array(':q' => '%' . $APPORTEUR . '%'));
    $apporteurs = $stmt->fetchAll();
    ?>

    <h2>Apporteur</h2>
    <?php if (!$apporteurs): ?>
        <p class="no">Aucun apporteur « <?php echo h($APPORTEUR); ?> » trouvé dans <code>jl_app</code>.</p>
        <div class="box">Le nom est peut-être orthographié différemment. Ouvre
            <a href="?t=jl_app">la table jl_app</a> pour vérifier l'orthographe exacte.</div>
    <?php else:
        $ids = array();
        foreach ($apporteurs as $a) { $ids[] = (int) $a['id']; }
        ?>
        <table>
            <tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Société</th></tr>
            <?php foreach ($apporteurs as $a): ?>
            <tr><td><?php echo (int)$a['id']; ?></td><td><strong><?php echo h($a['nom']); ?></strong></td>
                <td><?php echo h($a['prenom']); ?></td><td><?php echo h($a['societe']); ?></td></tr>
            <?php endforeach; ?>
        </table>

        <?php
        // 2) Contrats (jl_pro) de ce(s) apporteur(s) + client + compagnie.
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT p.id, p.num_contrat, p.num_devis, p.designation,
                       p.date_effet, p.date_echeance, p.prime, p.com_app, p.status,
                       cl.nom AS client_nom, cl.prenom AS client_prenom, cl.ville AS client_ville,
                       co.compagnie AS compagnie
                FROM jl_pro p
                LEFT JOIN jl_client cl ON p.id_cli = cl.id
                LEFT JOIN jl_compagnie co ON p.id_comp = co.id
                WHERE p.id_app IN (' . $in . ')
                ORDER BY p.date_effet DESC, p.id DESC';
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $contrats = $st->fetchAll();

        $nb = count($contrats);
        $totPrime = 0; $totCom = 0;
        foreach ($contrats as $c) {
            $totPrime += (float) str_replace(array(' ', ','), array('', '.'), $c['prime']);
            $totCom   += (float) str_replace(array(' ', ','), array('', '.'), $c['com_app']);
        }
        ?>

        <h2>Contrats (<?php echo $nb; ?>)</h2>
        <div class="stats">
            <div class="stat"><b><?php echo $nb; ?></b><span>Contrats</span></div>
            <div class="stat"><b><?php echo euros($totPrime); ?></b><span>Primes cumulées</span></div>
            <div class="stat"><b><?php echo euros($totCom); ?></b><span>Commissions apporteur</span></div>
        </div>

        <?php if (!$contrats): ?>
            <p class="muted">Aucun contrat trouvé pour cet apporteur dans <code>jl_pro</code>.</p>
        <?php else: ?>
        <div style="overflow:auto">
        <table>
            <thead><tr>
                <th>N° contrat</th><th>Client</th><th>Ville</th><th>Compagnie</th>
                <th>Désignation</th><th>Date effet</th><th>Échéance</th>
                <th class="num">Prime</th><th class="num">Com. app.</th><th>Statut</th>
            </tr></thead>
            <tbody>
            <?php foreach ($contrats as $c): ?>
                <tr>
                    <td><?php echo h($c['num_contrat'] !== '' && $c['num_contrat'] !== null ? $c['num_contrat'] : $c['num_devis']); ?></td>
                    <td><?php echo h(trim($c['client_nom'] . ' ' . $c['client_prenom'])); ?></td>
                    <td><?php echo h($c['client_ville']); ?></td>
                    <td><?php echo h($c['compagnie']); ?></td>
                    <td><?php echo h($c['designation']); ?></td>
                    <td><?php echo dateFr($c['date_effet']); ?></td>
                    <td><?php echo dateFr($c['date_echeance']); ?></td>
                    <td class="num"><?php echo euros($c['prime']); ?></td>
                    <td class="num"><?php echo euros($c['com_app']); ?></td>
                    <td><?php echo h($c['status']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <p class="tools">Outil : <a href="?t=jl_pro">structure jl_pro</a> · <a href="?t=jl_app">jl_app</a> · <a href="?t=jl_client">jl_client</a></p>

<?php endif; ?>

</body>
</html>
