<?php
/**
 * Outil de découverte du schéma JLASSURE.
 * Liste les tables et colonnes de la base JLASSURE afin d'ajuster
 * le `jlassure_mapping` dans config/config.php.
 *
 * Accès réservé aux utilisateurs connectés.
 */
require __DIR__ . '/../src/bootstrap.php';
Auth::require();

$pdo = Database::jlassure($CONFIG);
$selectedTable = (string) ($_GET['table'] ?? '');

$tables = [];
try {
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $tables[] = array_values($row)[0];
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$columns = [];
$sample = [];
if ($selectedTable !== '' && in_array($selectedTable, $tables, true)) {
    foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $selectedTable) . '`') as $col) {
        $columns[] = $col;
    }
    $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '', $selectedTable) . '` LIMIT 3');
    $sample = $stmt->fetchAll();
}

$pageTitle = 'Découverte du schéma JLASSURE';
require __DIR__ . '/../partials/header.php';
?>
<p><a href="../index.php">← Retour</a></p>
<h1>Schéma JLASSURE</h1>
<p class="hint">Repérez la table des contrats et les noms de colonnes réels,
puis reportez-les dans <code>config/config.php → jlassure_mapping</code>.</p>

<?php if (!empty($err)): ?>
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
                        <tr><?php foreach ($line as $v): ?><td><?= e(mb_strimwidth((string) $v, 0, 40, '…')) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
