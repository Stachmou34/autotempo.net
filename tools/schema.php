<?php
/**
 * Outil de découverte du schéma JLASSURE.
 * Liste les tables et colonnes de la base JLASSURE afin d'ajuster
 * le `jlassure_mapping` dans config/config.php.
 *
 * Accès réservé aux utilisateurs connectés. Compatible PHP 5.6+.
 */
require __DIR__ . '/../src/bootstrap.php';
Auth::requireLogin();

$pdo = Database::jlassure($CONFIG);
$selectedTable = isset($_GET['table']) ? $_GET['table'] : '';

$err = '';
$tables = array();
try {
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $vals = array_values($row);
        $tables[] = $vals[0];
    }
} catch (Exception $e) {
    $err = $e->getMessage();
}

$columns = array();
$sample = array();
if ($selectedTable !== '' && in_array($selectedTable, $tables, true)) {
    $safe = str_replace('`', '', $selectedTable);
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $safe . '`') as $col) {
        $columns[] = $col;
    }
    $stmt = $pdo->query('SELECT * FROM `' . $safe . '` LIMIT 3');
    $sample = $stmt->fetchAll();
}

$pageTitle = 'Découverte du schéma JLASSURE';
require __DIR__ . '/../partials/header.php';
?>
<p><a href="../index.php">← Retour</a></p>
<h1>Schéma JLASSURE</h1>
<p class="hint">Repérez la table des contrats et les noms de colonnes réels,
puis reportez-les dans <code>config/config.php → jlassure_mapping</code>.</p>

<?php if ($err !== ''): ?>
    <div class="alert error"><?= e($err) ?></div>
<?php endif; ?>

<div class="detail-grid">
    <section class="card">
        <h2>Tables (<?= count($tables) ?>)</h2>
        <ul class="table-list">
            <?php foreach ($tables as $t): ?>
                <li><a href="?table=<?= urlencode($t) ?>" <?= $t === $selectedTable ? 'class="active"' : '' ?>><?= e($t) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <?php if ($selectedTable === ''): ?>
            <p>Sélectionnez une table pour voir ses colonnes.</p>
        <?php else: ?>
            <h2>Colonnes de <code><?= e($selectedTable) ?></code></h2>
            <table class="grid">
                <thead><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th></tr></thead>
                <tbody>
                <?php foreach ($columns as $c): ?>
                    <tr>
                        <td><strong><?= e($c['Field']) ?></strong></td>
                        <td><?= e($c['Type']) ?></td>
                        <td><?= e($c['Null']) ?></td>
                        <td><?= e($c['Key']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($sample): ?>
                <h3>Aperçu (3 lignes)</h3>
                <div class="table-wrap">
                <table class="grid small">
                    <thead><tr><?php foreach (array_keys($sample[0]) as $k): ?><th><?= e($k) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($sample as $line): ?>
                        <tr><?php foreach ($line as $v): ?><td><?= e(truncate($v, 40)) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
